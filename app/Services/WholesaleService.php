<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WholesaleService
{
    private const PRICE_LIST_ID = 3;
    private const PURCHASE_PRICE_RATIO = 0.63;

    public function __construct(private OrderService $orderService) {}

    /**
     * Resuelve y valida cada item: producto publicado, precio mayorista y stock suficiente.
     * Lanza JsonResponse si algo falla (para que el controller la retorne directamente).
     *
     * @param array $items
     * @return array|JsonResponse
     */
    public function resolveItems(array $items): array|JsonResponse
    {
        $resolved = [];
        $stockErrors = [];

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $variantId = $item['variant_id'] ?? null;
            $qty       = $item['quantity'];

            $product = Product::with([
                    'priceLists' => fn($q) => $q->where('price_list_id', self::PRICE_LIST_ID),
                    'suppliers',
                ])
                ->where('id', $productId)
                ->where('status', ProductStatus::Published)
                ->first();

            if (!$product) {
                return response()->json(['message' => "Producto #{$productId} no disponible."], 422);
            }

            $unitPrice = $product->priceLists->first()?->pivot?->price;
            if (!$unitPrice) {
                return response()->json(['message' => "Producto #{$productId} sin precio mayorista."], 422);
            }

            if ($variantId) {
                $variant = ProductVariant::where('id', $variantId)
                    ->where('product_id', $productId)
                    ->where('is_active', true)
                    ->first();

                if (!$variant) {
                    return response()->json(['message' => "Variante #{$variantId} no encontrada o inactiva."], 422);
                }

                if ($variant->stock < $qty) {
                    $stockErrors[] = [
                        'product_id' => $productId,
                        'variant_id' => $variantId,
                        'name'       => $product->name,
                        'available'  => (int) $variant->stock,
                        'requested'  => $qty,
                    ];
                    continue;
                }
            } else {
                if ($product->stock < $qty) {
                    $stockErrors[] = [
                        'product_id' => $productId,
                        'variant_id' => null,
                        'name'       => $product->name,
                        'available'  => (int) $product->stock,
                        'requested'  => $qty,
                    ];
                    continue;
                }
            }

            $unitPriceFloat = (float) $unitPrice;
            $resolved[] = [
                'product_id'     => $productId,
                'variant_id'     => $variantId,
                'quantity'       => $qty,
                'unit_price'     => $unitPriceFloat,
                'purchase_price' => (float) ($product->suppliers->first()?->pivot->purchase_price
                    ?? round($unitPriceFloat * self::PURCHASE_PRICE_RATIO, 2)),
                'subtotal'       => round($unitPriceFloat * $qty, 2),
            ];
        }

        if (!empty($stockErrors)) {
            return response()->json([
                'message'      => 'Algunos productos se quedaron sin stock mientras finalizabas la compra.',
                'stock_errors' => $stockErrors,
            ], 409);
        }

        return $resolved;
    }

    /**
     * Crea el pedido completo dentro de una transacción:
     * upsert de cliente, orden, detalles y descuento de stock.
     *
     * @param array $data    Datos validados del request (name, email, phone, shipping_address, notes)
     * @param array $items   Items ya resueltos por resolveItems()
     * @return Order
     */
    public function createOrder(array $data, array $items): Order
    {
        return DB::transaction(function () use ($data, $items) {
            $client = $this->upsertClient($data);

            $order = Order::create([
                'client_id'          => $client->id,
                'status'             => OrderStatus::Pending,
                'price_list_id'      => self::PRICE_LIST_ID,
                'total_amount'       => 0,
                'final_total_amount' => 0,
                'shipping_address'   => $data['shipping_address'] ?? null,
                'delivery_method'    => $data['delivery_method'],
                'notes'              => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);

                [$discPct, $discFixed, $promoId] = $this->orderService->calculatePromotionForLine(
                    $order, $product, $item['quantity'], $item['unit_price']
                );

                $subtotal = $item['quantity'] * $item['unit_price'];
                $subtotalWithDiscount = ($subtotal - $discFixed) * (1 - $discPct / 100);

                OrderDetail::create([
                    'order_id'               => $order->id,
                    'product_id'             => $item['product_id'],
                    'variant_id'             => $item['variant_id'],
                    'quantity'               => $item['quantity'],
                    'purchase_price'         => $item['purchase_price'],
                    'unit_price'             => $item['unit_price'],
                    'discount_percentage'    => $discPct,
                    'discount_fixed_amount'  => $discFixed,
                    'promotion_id'           => $promoId,
                    'subtotal'               => $subtotal,
                    'subtotal_with_discount' => $subtotalWithDiscount,
                ]);

                $this->decrementStock($item);
            }

            $this->orderService->calculateOrderTotals($order);

            return $order->fresh();
        });
    }

    private function upsertClient(array $data): Client
    {
        $client = Client::firstOrCreate(
            ['email' => $data['email']],
            [
                'name'          => $data['name'],
                'phone'         => $data['phone'] ?? null,
                'price_list_id' => self::PRICE_LIST_ID,
                'password'      => null,
            ]
        );

        if (!$client->wasRecentlyCreated) {
            $client->update([
                'name'  => $data['name'],
                'phone' => $data['phone'] ?? $client->phone,
            ]);
        }

        return $client;
    }

    private function decrementStock(array $item): void
    {
        $qty = $item['quantity'];

        if ($item['variant_id']) {
            $affected = ProductVariant::where('id', $item['variant_id'])
                ->where('stock', '>=', $qty)
                ->decrement('stock', $qty);
        } else {
            $affected = Product::where('id', $item['product_id'])
                ->where('stock', '>=', $qty)
                ->decrement('stock', $qty);
        }

        if ($affected === 0) {
            $this->throwInsufficientStock($item);
        }
    }

    private function throwInsufficientStock(array $item): void
    {
        $variantId = $item['variant_id'] ?? null;
        $product   = Product::find($item['product_id']);
        $available = $variantId
            ? (int) (ProductVariant::find($variantId)?->stock ?? 0)
            : (int) ($product?->stock ?? 0);

        throw new InsufficientStockException([[
            'product_id' => $item['product_id'],
            'variant_id' => $variantId,
            'name'       => $product?->name ?? 'Producto',
            'available'  => $available,
            'requested'  => $item['quantity'],
        ]]);
    }
}
