<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionController
{
    /**
     * Lista promociones con filtros opcionales.
     */
    public function index(Request $request)
    {
        $rules = [
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];

        $params = [
            'is_active.boolean' => 'El campo is_active debe ser true (1) o false (0).',
            'per_page.integer' => 'El campo per_page debe ser un número entero.',
            'per_page.min' => 'El campo per_page debe ser al menos 1.',
            'per_page.max' => 'El campo per_page no puede ser mayor a 100.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            $query = Promotion::query()->with(['products:id,name,sku', 'priceLists:id,name']);

            if (isset($validated['is_active'])) {
                $query->where('is_active', (bool) $validated['is_active']);
            }

            $perPage = $validated['per_page'] ?? 20;
            $promotions = $query->latest()->paginate($perPage);

            return response()->json($promotions);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener las promociones.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crea una nueva promoción (opcionalmente con productos y listas de precio).
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
            'discount_type' => 'required|in:percentage,fixed_amount,second_unit_percentage',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|integer|min:1',

            'product_ids' => 'nullable|array',
            'product_ids.*' => 'uuid|exists:products,id',
            'price_list_ids' => 'nullable|array',
            'price_list_ids.*' => 'integer|exists:price_lists,id',
        ];

        $messages = [
            'name.required' => 'El nombre de la promoción es obligatorio.',
            'discount_type.required' => 'El tipo de descuento es obligatorio.',
            'discount_type.in' => 'El tipo de descuento debe ser: percentage, fixed_amount o second_unit_percentage.',
            'discount_value.required' => 'El valor del descuento es obligatorio.',
            'product_ids.*.exists' => 'Uno de los productos no existe.',
            'price_list_ids.*.exists' => 'Una de las listas de precios no existe.',
        ];

        try {
            $validated = $request->validate($rules, $messages);

            $promotion = DB::transaction(function () use ($validated) {
                $data = collect($validated)->only([
                    'name', 'description', 'starts_at', 'ends_at', 'is_active',
                    'discount_type', 'discount_value', 'max_discount_amount', 'min_quantity',
                ])->filter(fn ($v) => $v !== null)->all();

                if (!isset($data['is_active'])) {
                    $data['is_active'] = true;
                }
                if (!isset($data['min_quantity'])) {
                    $data['min_quantity'] = 1;
                }

                $promotion = Promotion::create($data);

                if (!empty($validated['product_ids'])) {
                    $promotion->products()->sync($validated['product_ids']);
                }
                if (!empty($validated['price_list_ids'])) {
                    $promotion->priceLists()->sync($validated['price_list_ids']);
                }

                return $promotion->load('products:id,name,sku', 'priceLists:id,name');
            });

            return response()->json($promotion, 201);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json([
                'message' => 'Uno o más productos ya pertenecen a otra promoción. Cada producto solo puede estar en una promoción.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear la promoción.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra una promoción con sus productos y listas de precio.
     */
    public function show(Promotion $promotion)
    {
        $promotion->load('products:id,name,sku', 'priceLists:id,name');
        return response()->json($promotion);
    }

    /**
     * Actualiza una promoción (campos opcionales; productos y listas se pueden enviar para reemplazar).
     */
    public function update(Request $request, Promotion $promotion)
    {
        $rules = [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'discount_type' => 'nullable|in:percentage,fixed_amount,second_unit_percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|integer|min:1',

            'product_ids' => 'nullable|array',
            'product_ids.*' => 'uuid|exists:products,id',
            'price_list_ids' => 'nullable|array',
            'price_list_ids.*' => 'integer|exists:price_lists,id',
        ];

        $messages = [
            'product_ids.*.exists' => 'Uno de los productos no existe.',
            'price_list_ids.*.exists' => 'Una de las listas de precios no existe.',
        ];

        try {
            $validated = $request->validate($rules, $messages);

            $promotion = DB::transaction(function () use ($promotion, $validated) {
                $data = collect($validated)->only([
                    'name', 'description', 'starts_at', 'ends_at', 'is_active',
                    'discount_type', 'discount_value', 'max_discount_amount', 'min_quantity',
                ])->filter(fn ($v) => $v !== null)->all();

                $promotion->update($data);

                if (array_key_exists('product_ids', $validated)) {
                    $promotion->products()->sync($validated['product_ids'] ?? []);
                }
                if (array_key_exists('price_list_ids', $validated)) {
                    $promotion->priceLists()->sync($validated['price_list_ids'] ?? []);
                }

                return $promotion->fresh(['products:id,name,sku', 'priceLists:id,name']);
            });

            return response()->json($promotion);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json([
                'message' => 'Uno o más productos ya pertenecen a otra promoción. Cada producto solo puede estar en una promoción.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la promoción.',
                'message' => $e->getMessage(),
            ], 500);
        }
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
     * Un producto no puede estar en más de una promoción.
     */
    public function syncProducts(Request $request, Promotion $promotion)
    {
        $rules = [
            // `present` exige que llegue el campo, pero permite array vacío ([])
            'product_ids' => 'present|array',
            'product_ids.*' => 'uuid|exists:products,id',
        ];

        $messages = [
            'product_ids.present' => 'Debes enviar el campo product_ids (puede ser array vacío para quitar todos).',
            'product_ids.*.exists' => 'Uno de los productos no existe.',
        ];

        try {
            $validated = $request->validate($rules, $messages);
            $promotion->products()->sync($validated['product_ids']);
            $promotion->load('products:id,name,sku');
            return response()->json($promotion);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return response()->json([
                'message' => 'Uno o más productos ya pertenecen a otra promoción. Cada producto solo puede estar en una promoción.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar los productos de la promoción.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Asocia listas de precio a la promoción (reemplaza las actuales).
     * Si queda vacío, la promoción aplica a todas las listas.
     */
    public function syncPriceLists(Request $request, Promotion $promotion)
    {
        $rules = [
            'price_list_ids' => 'required|array',
            'price_list_ids.*' => 'integer|exists:price_lists,id',
        ];

        $messages = [
            'price_list_ids.required' => 'Debes enviar el array de listas (puede ser vacío).',
            'price_list_ids.*.exists' => 'Una de las listas de precios no existe.',
        ];

        try {
            $validated = $request->validate($rules, $messages);
            $promotion->priceLists()->sync($validated['price_list_ids']);
            $promotion->load('priceLists:id,name');
            return response()->json($promotion);
        } catch (ValidationException $e) {
            return response()->json($e->errors(), 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar las listas de precio de la promoción.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
