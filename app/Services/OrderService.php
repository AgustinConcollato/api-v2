<?php

// app/Services/OrderService.php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Summary of createOrder
     * @param array $data
     * @return Order
     */
    public function createOrder(array $data): Order
    {
        return Order::create([
            ...$data,
            'status' => OrderStatus::Processing,
            'total_amount' => 0.00,
            'final_total_amount' => 0.00,
        ]);
    }

    /**
     * Summary of searchOrders
     * @param array $params
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function searchOrders(array $data)
    {
        $query = Order::with(['client', 'payments', 'details']);

        // Filtro por Status
        $query->when(isset($data['status']), function ($q) use ($data) {
            $q->where('status', $data['status']);
        });

        // Filtro por Cliente
        $query->when(isset($data['client_id']), function ($q) use ($data) {
            $q->where('client_id', $data['client_id']);
        });

        // Filtro por Fechas (Rango manual)
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $query->whereBetween('created_at', [
                $data['start_date'] . ' 00:00:00',
                $data['end_date'] . ' 23:59:59'
            ]);
        }
        // Filtro por Fechas (Rango rápido)
        if (isset($data['start_date']) && isset($data['end_date']) && $data['start_date'] !== '' && $data['end_date'] !== '') {
            // Rango manual
            $query->whereBetween('created_at', [
                $data['start_date'] . ' 00:00:00',
                $data['end_date'] . ' 23:59:59'
            ]);
        } elseif (isset($data['range'])) {
            if ($data['range'] === 'week') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($data['range'] === 'month') {
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
            }
            // Si $data['range'] === 'all', simplemente no entra en los if anteriores 
            // y no se aplica ningún filtro de fecha, trayendo todo el historial.
        }

        $orders = $query->latest()->paginate(20);

        // Transformamos los resultados dentro del servicio antes de devolverlos
        $orders->getCollection()->transform(function ($order) {
            $order->balance_due = $this->getPendingBalance($order);
            $order->total_cost = $order->getTotalCostAttribute();
            return $order;
        });

        return $orders;
    }

    /**
     * Añade un producto al pedido, actualiza el stock y recalcula totales.
     * @param Order $order El pedido.
     * @param array $item Los detalles del producto a añadir.
     * @return OrderDetail
     */
    public function addProductToOrder(Order $order, array $item): OrderDetail
    {
        return DB::transaction(function () use ($order, $item) {
            $product = Product::find($item['product_id']);

            if (!$product) {
                throw new \Exception("Producto no encontrado.");
            }

            if ($order->status !== OrderStatus::Processing) {
                throw ValidationException::withMessages([
                    'status' => ["El pedido no esta en preparación, no se pueden agregar más productos."],
                ]);
            }

            // --- BÚSQUEDA DEL DETALLE EXISTENTE ---
            // Intentamos encontrar un detalle para este producto en la orden
            $detail = $order->details()->where('product_id', $product->id)->first();

            // 1. Validar Stock (ahora se valida el stock restante después de la potencial suma)
            // La nueva cantidad será la cantidad actual del item + la cantidad que ya existe en el detalle (si existe)
            $quantityToAdd = $item['quantity'];
            $currentDetailQuantity = $detail ? $detail->quantity : 0;
            $newTotalQuantity = $currentDetailQuantity + $quantityToAdd;

            if ($product->stock < $quantityToAdd) {
                // La validación de stock debe hacerse sobre la cantidad NUEVA que se intenta agregar
                throw ValidationException::withMessages([
                    'quantity' => ["Stock insuficiente. Disponible: {$product->stock}."],
                ]);
            }

            // --- 2. CREAR/ACTUALIZAR DETALLE ---
            if ($detail) {
                // El producto ya existe, solo sumamos la cantidad
                $detail->quantity = $newTotalQuantity;

                $detail->unit_price = $item['unit_price'];
                $detail->purchase_price = $item['purchase_price'];
            } else {
                // El producto NO existe, creamos un nuevo detalle
                $detail = $order->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantityToAdd,
                    'purchase_price' => $item['purchase_price'],
                    'unit_price' => $item['unit_price'],
                    'discount_percentage' => 0.00,
                    'discount_fixed_amount' => 0.00,
                    // Los campos de subtotal se calcularán y guardarán más abajo
                    'subtotal' => 0,
                    'subtotal_with_discount' => 0,
                ]);
            }

            // --- 3. APLICAR PROMOCIÓN (si existe) ---
            // Se calcula siempre usando los valores finales de cantidad y precio unitario
            [$discountPercentage, $discountFixedAmount, $promotionId] = $this->calculatePromotionForLine(
                $order,
                $product,
                $detail->quantity,
                $detail->unit_price
            );

            $detail->discount_percentage = $discountPercentage;
            $detail->discount_fixed_amount = $discountFixedAmount;
            $detail->promotion_id = $promotionId;

            // --- 4. CALCULAR SUBTOTAL DE LÍNEA (Usando los valores FINALES del detalle) ---
            // Se recalcula usando la nueva cantidad total ($detail->quantity) y los precios/descuentos aplicados
            $subtotal = $detail->quantity * $detail->unit_price;
            $subtotalWithDiscount = $subtotal - $detail->discount_fixed_amount;
            $subtotalWithDiscount *= (1 - ($detail->discount_percentage / 100));

            $detail->subtotal = $subtotal;
            $detail->subtotal_with_discount = $subtotalWithDiscount;
            $detail->save(); // Guardar el detalle (actualizado o recién creado)

            // --- 5. AJUSTAR STOCK ---
            // Solo restamos la cantidad que SE AGREGÓ AHORA ($quantityToAdd), 
            // ya que el stock de las unidades que ya estaban en el pedido ya fue restado antes.
            $product->stock -= $quantityToAdd;
            $product->save();

            // --- 6. RECALCULAR TOTALES DEL PEDIDO ---
            $this->calculateOrderTotals($order);

            return $detail;
        });
    }

    /**
     * Elimina un producto del pedido, devuelve el stock y recalcula totales.
     * @param OrderDetail $detail El detalle de la línea a eliminar.
     * @return bool
     */
    public function removeProductFromOrder(OrderDetail $detail): bool
    {
        return DB::transaction(function () use ($detail) {
            $order = $detail->order;
            $product = $detail->product;

            if ($order->status !== OrderStatus::Processing) {
                throw ValidationException::withMessages([
                    'status' => ["El pedido no esta en preparación, no se pueden eliminar los productos."],
                ]);
            }
            // 1. DEVOLVER STOCK
            if ($product) {
                $product->stock += $detail->quantity;
                $product->save();
            }

            // 2. ELIMINAR DETALLE
            $detail->delete();

            // 3. RECALCULAR TOTALES DEL PEDIDO
            $this->calculateOrderTotals($order);

            return true;
        });
    }


    /**
     * Actualiza la cabecera de la orden con cliente, estado y descuentos.
     * Luego recalcula los totales finales.
     * @param Order $order El pedido a actualizar.
     * @param array $data Los datos a actualizar (client_id, order_status, discount_percentage, etc.).
     * @return Order
     */
    public function updateOrderHeader(Order $order, array $data): Order
    {
        // Campos que se pueden actualizar:
        $updatableFields = [
            'client_id',
            'status',
            'shipping_address',
            'notes',
            'discount_percentage',
            'discount_fixed_amount',
            'shipping_cost',
        ];

        if (isset($data['status'])) {
            // Al usar OrderStatus::from(), transformas el string del request en un objeto Enum
            $newStatus = OrderStatus::from($data['status']);

            if (!$order->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => ["Transición no permitida."],
                ]);
            }
        }

        if (isset($data['discount_percentage']) || isset($data['discount_fixed_amount']) || isset($data['shipping_cost'])) {
            if (!in_array($order->status, [OrderStatus::Confirmed, OrderStatus::Processing])) {
                throw ValidationException::withMessages([
                    'status' => ["Solo se pueden editar costos y descuentos en pedidos en preparación o terminados."],
                ]);
            }
        }

        // si se modifica el esta a "cancelled", se debe devolver el stock de todos los productos
        if (isset($data['status']) && $data['status'] === 'cancelled' && $order->status !== 'cancelled') {
            foreach ($order->details as $detail) {
                $product = Product::find($detail->product_id);
                if ($product) {
                    $product->increment('stock', $detail->quantity);
                }
            }
        }

        // Filtrar solo los datos que corresponden a los campos actualizables
        $updateData = collect($data)->only($updatableFields)->filter(function ($value) {
            // Asegura que solo se incluyan valores no nulos o vacíos si son enviados
            return !is_null($value);
        })->all();

        // Aplicar la actualización
        $order->update($updateData);

        // Recalcular los totales finales (es crucial si se cambian descuentos o costos de envío)
        $this->calculateOrderTotals($order);

        return $order->fresh(); // Devolver el modelo recién actualizado
    }

    /**
     * Modifica la cantidad, precios o descuentos de un producto en la línea de pedido.
     * Devuelve o resta la diferencia de stock y recalcula totales.
     * * @param OrderDetail $detail El detalle de la línea a modificar.
     * @param array $data Los datos a actualizar (quantity, unit_price, discount_percentage, etc.).
     * @return OrderDetail
     */
    public function updateProductInOrder(OrderDetail $detail, array $data): OrderDetail
    {
        return DB::transaction(function () use ($detail, $data) {
            $order = $detail->order;
            $product = $detail->product;

            // 1. VALIDAR ESTADO DEL PEDIDO
            if ($order->status !== OrderStatus::Processing) {
                throw ValidationException::withMessages([
                    'status' => ["El pedido no esta en preparación, no se pueden editar los productos."],
                ]);
            }

            // 2. PREPARAR DATOS Y VALIDAR STOCK
            $currentQuantity = $detail->quantity;
            $newQuantity = $data['quantity'] ?? $currentQuantity;
            $quantityDifference = $newQuantity - $currentQuantity; // Positivo si se suma, negativo si se resta

            if ($quantityDifference > 0) {
                // Si se está aumentando la cantidad, validar el stock disponible para la diferencia
                if ($product->stock < $quantityDifference) {
                    throw ValidationException::withMessages([
                        'quantity' => ["Stock insuficiente para aumentar la cantidad. Disponible: {$product->stock}."],
                    ]);
                }
            }

            // 3. ACTUALIZAR CAMPOS BÁSICOS
            // Filtramos solo los campos que vienen en el request para no sobreescribir con null
            $updatableFields = ['quantity', 'unit_price', 'purchase_price'];
            foreach ($updatableFields as $field) {
                if (isset($data[$field])) {
                    $detail->{$field} = $data[$field];
                }
            }

            // 4. RECALCULAR PROMOCIÓN (Punto clave corregido)
            // Al cambiar la cantidad o el precio unitario, debemos verificar si la promo sigue siendo válida
            [$discountPercentage, $discountFixedAmount, $promotionId] = $this->calculatePromotionForLine(
                $order,
                $product,
                $detail->quantity,
                $detail->unit_price
            );

            $detail->discount_percentage = $discountPercentage;
            $detail->discount_fixed_amount = $discountFixedAmount;
            $detail->promotion_id = $promotionId;

            // 5. CALCULAR SUBTOTALES DE LÍNEA
            $subtotal = $detail->quantity * $detail->unit_price;
            $subtotalWithDiscount = $subtotal - $detail->discount_fixed_amount;
            $subtotalWithDiscount *= (1 - ($detail->discount_percentage / 100));

            $detail->subtotal = $subtotal;
            $detail->subtotal_with_discount = $subtotalWithDiscount;

            // Guardar todos los cambios del detalle
            $detail->save();

            // 6. AJUSTAR STOCK DEL PRODUCTO
            if ($product) {
                $product->stock -= $quantityDifference;
                $product->save();
            }

            // 7. RECALCULAR TOTALES DEL PEDIDO
            $this->calculateOrderTotals($order);

            return $detail;
        });
    }


    /**
     * Obtiene el monto total pagado de un pedido.
     * @param Order $order
     * @return float
     */
    public function getPaidAmount(Order $order): float
    {
        return (float) $order->payments()
            ->where('status', PaymentStatus::Completed)
            ->sum('amount');
    }

    /**
     * Obtiene el saldo pendiente de un pedido.
     * @param Order $order
     * @return float
     */
    public function getPendingBalance(Order $order): float
    {
        $paid = $this->getPaidAmount($order);
        $total = (float) $order->final_total_amount;

        return max(0, $total - $paid);
    }

    /**
     * Recalcula y actualiza todos los montos de la orden (total_amount, final_total_amount).
     * @param Order $order
     */
    public function calculateOrderTotals(Order $order): void
    {
        $order->refresh(); // Asegura que tenemos los últimos detalles

        // 1. CALCULAR MONTO TOTAL BRUTO (suma de subtotales de las líneas)
        $totalAmount = $order->details()->sum('subtotal_with_discount');
        $finalTotal = $totalAmount;

        // 2. APLICAR DESCUENTOS Y GASTOS GLOBALES
        $finalTotal -= $order->discount_fixed_amount;
        $finalTotal *= (1 - ($order->discount_percentage / 100));
        $finalTotal += $order->shipping_cost;

        // 3. ACTUALIZAR LA ORDEN
        $order->update([
            'total_amount' => $totalAmount,
            'final_total_amount' => max(0, $finalTotal),
        ]);
    }

    /**
     * Determina si hay una promoción aplicable para una línea y calcula los descuentos.
     *
     * Reglas:
     * - Si el producto está en una promoción activa (fecha + is_active)
     * - Y la promoción aplica a la lista de precios del pedido (o a todas si no tiene listas asociadas)
     * - Entonces calcula:
     *   - percentage: porcentaje sobre el subtotal, respetando tope max_discount_amount.
     *   - fixed_amount: monto fijo (limitado por subtotal y tope).
     *   - second_unit_percentage: desc. sobre la 2da unidad por cada par, respetando tope.
     *
     * @return array [discount_percentage, discount_fixed_amount, promotion_id|null]
     */
    protected function calculatePromotionForLine(
        Order $order,
        Product $product,
        int $quantity,
        float $unitPrice
    ): array {
        $promotion = $this->findApplicablePromotion($order, $product);

        if (!$promotion || $quantity < $promotion->min_quantity) {
            return [0.0, 0.0, null];
        }

        $subtotal = $quantity * $unitPrice;
        $discountPercentage = 0.0;
        $discountFixed = 0.0;

        switch ($promotion->discount_type) {
            case 'percentage':
                $baseDiscount = $subtotal * ($promotion->discount_value / 100);

                if ($promotion->max_discount_amount !== null && $baseDiscount > $promotion->max_discount_amount) {
                    // Se aplica el tope como monto fijo
                    $discountFixed = $promotion->max_discount_amount;
                } else {
                    $discountPercentage = $promotion->discount_value;
                }
                break;

            case 'fixed_amount':
                $discountFixed = min($promotion->discount_value, $subtotal);

                if ($promotion->max_discount_amount !== null && $discountFixed > $promotion->max_discount_amount) {
                    $discountFixed = $promotion->max_discount_amount;
                }
                break;

            case 'second_unit_percentage':
                // Para cada par de unidades, la segunda tiene X% de descuento
                $pairs = intdiv($quantity, 2);
                if ($pairs > 0) {
                    $baseDiscount = $pairs * $unitPrice * ($promotion->discount_value / 100);

                    if ($promotion->max_discount_amount !== null && $baseDiscount > $promotion->max_discount_amount) {
                        $discountFixed = $promotion->max_discount_amount;
                    } else {
                        $discountFixed = $baseDiscount;
                    }
                }
                break;
        }

        return [$discountPercentage, $discountFixed, $promotion->id];
    }

    /**
     * Busca una promoción válida para un producto en el contexto de un pedido.
     *
     * - Debe estar activa (scope active()).
     * - El producto debe estar vinculado a la promoción.
     * - Si la promoción tiene listas de precio asociadas, debe incluir la del pedido.
     */
    protected function findApplicablePromotion(Order $order, Product $product): ?Promotion
    {
        /** @var Promotion|null $promotion */
        $promotion = $product->promotions()
            ->active()
            ->where(function ($q) use ($order) {
                $q->whereDoesntHave('priceLists')
                    ->orWhereHas('priceLists', function ($q2) use ($order) {
                        $q2->where('price_lists.id', $order->price_list_id);
                    });
            })
            ->first();

        return $promotion;
    }
}
