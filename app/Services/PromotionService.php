<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    /**
     * Lista promociones con filtros opcionales.
     */
    public function index(array $data)
    {
        $query = Promotion::query()->with(['products:id,name,sku', 'priceLists:id,name']);

        if (isset($data['is_active'])) {
            $query->where('is_active', (bool) $data['is_active']);
        }

        $perPage = $data['per_page'] ?? 20;
        $promotions = $query->latest()->paginate($perPage);

        return $promotions;
    }

    /**
     * Crea una nueva promoción (opcionalmente con productos y listas de precio).
     */
    public function store(array $data): Promotion
    {
        $updatableFields = [
            'name',
            'description',
            'starts_at',
            'ends_at',
            'is_active',
            'discount_type',
            'discount_value',
            'max_discount_amount',
            'min_quantity',
        ];

        $promotion = DB::transaction(function () use ($data, $updatableFields) {
            $productIds = array_key_exists('product_ids', $data) ? ($data['product_ids'] ?? []) : null;
            $priceListIds = array_key_exists('price_list_ids', $data) ? ($data['price_list_ids'] ?? []) : null;

            $createData = collect($data)->only($updatableFields)->filter(fn($v) => $v !== null)->all();

            if (!isset($createData['is_active'])) {
                $createData['is_active'] = true;
            }
            if (!isset($createData['min_quantity'])) {
                $createData['min_quantity'] = 1;
            }

            $promotion = Promotion::create($createData);

            if ($productIds !== null) {
                $promotion->products()->sync($productIds);
            }
            if ($priceListIds !== null) {
                $promotion->priceLists()->sync($priceListIds);
            }

            return $promotion->load('products.images', 'priceLists:id,name');
        });

        return $promotion;
    }

    /**
     * Muestra una promoción con sus productos y listas de precio.
     */
    public function show(Promotion $promotion)
    {
        $promotion->load('priceLists:id,name');
        $promotion->products->each(function ($product) {
            $product->load('images');
            $product->load('priceLists');
        });
        return $promotion;
    }

    /**
     * Actualiza una promoción (campos opcionales; productos y listas se pueden enviar para reemplazar).
     */
    public function update(Promotion $promotion, array $data)
    {
        $updatableFields = [
            'name',
            'description',
            'starts_at',
            'ends_at',
            'is_active',
            'discount_type',
            'discount_value',
            'max_discount_amount',
            'min_quantity',
        ];

        $promotion = DB::transaction(function () use ($promotion, $data, $updatableFields) {
            $productIds = array_key_exists('product_ids', $data) ? ($data['product_ids'] ?? []) : null;
            $priceListIds = array_key_exists('price_list_ids', $data) ? ($data['price_list_ids'] ?? []) : null;

            $updateData = collect($data)->only($updatableFields)->filter(fn($v) => $v !== null)->all();

            $promotion->update($updateData);

            if ($productIds !== null) {
                $promotion->products()->sync($productIds);
            }

            if ($priceListIds !== null) {
                $promotion->priceLists()->sync($priceListIds);
            }

            return $promotion->fresh(['products:id,name,sku', 'priceLists:id,name']);
        });

        return $promotion;
    }

    /**
     * Elimina una promoción.
     */
    public function destroy(Promotion $promotion)
    {
        try {
            $promotion->delete();
            return response()->json(['message' => 'Promoción eliminada correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar la promoción.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asocia productos a la promoción (reemplaza los actuales).
     * Cada item puede tener overrides opcionales de condiciones (patrón plan-suscriptor).
     * Un producto no puede estar en más de una promoción.
     *
     * @param array $products  Array de ['id' => uuid, ...overrides opcionales]
     */
    public function syncProducts(Promotion $promotion, array $products): Promotion
    {
        $overrideFields = ['discount_type', 'discount_value', 'max_discount_amount', 'min_quantity'];

        $syncData = collect($products)->mapWithKeys(function ($item) use ($overrideFields) {
            $overrides = collect($item)->only($overrideFields)->filter(fn($v) => $v !== null)->all();
            return [$item['id'] => $overrides];
        })->all();

        $promotion->products()->sync($syncData);
        return $promotion->load('products:id,name,sku');
    }

    /**
     * Asocia listas de precio a la promoción (reemplaza las actuales).
     * Si queda vacío, la promoción aplica a todas las listas.
     */
    public function syncPriceLists(Promotion $promotion, array $priceListIds)
    {
        $promotion->priceLists()->sync($priceListIds);
        return $promotion->load('priceLists:id,name');
    }
}
