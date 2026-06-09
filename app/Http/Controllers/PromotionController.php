<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PromotionController
{

    private $promotionService;
    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }
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

            $promotions =  $this->promotionService->index($validated);

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

        $params = [
            'name.required' => 'El nombre de la promoción es obligatorio.',
            'discount_type.required' => 'El tipo de descuento es obligatorio.',
            'discount_type.in' => 'El tipo de descuento debe ser: percentage, fixed_amount o second_unit_percentage.',
            'discount_value.required' => 'El valor del descuento es obligatorio.',
            'product_ids.*.exists' => 'Uno de los productos no existe.',
            'price_list_ids.*.exists' => 'Una de las listas de precios no existe.',
            'starts_at.date' => 'La fecha de inicio debe ser una fecha válida.',
            'ends_at.date' => 'La fecha de fin debe ser una fecha válida.',
            'ends_at.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'is_active.boolean' => 'El campo is_active debe ser true (1) o false (0).',
            'min_quantity.integer' => 'La cantidad mínima debe ser un número entero.',
            'min_quantity.min' => 'La cantidad mínima debe ser al menos 1.',
            'max_discount_amount.numeric' => 'El tope de descuento debe ser un número.',
            'max_discount_amount.min' => 'El tope de descuento debe ser al menos 0.',
            'product_ids.array' => 'El campo product_ids debe ser un array.',
            'product_ids.*.uuid' => 'Uno de los productos no es válido.',
            'price_list_ids.array' => 'El campo price_list_ids debe ser un array.',
            'price_list_ids.*.integer' => 'Una de las listas de precios no es válida.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            $promotion = $this->promotionService->store($validated);

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
        return $this->promotionService->show($promotion);
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
            $promotion = $this->promotionService->update($promotion, $validated);

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
     * Cada producto puede tener overrides opcionales de condiciones.
     */
    public function syncProducts(Request $request, Promotion $promotion)
    {
        $rules = [
            'products'                       => 'present|array',
            'products.*.id'                  => 'required|uuid|exists:products,id',
            'products.*.discount_type'       => 'nullable|in:percentage,fixed_amount,second_unit_percentage',
            'products.*.discount_value'      => 'nullable|numeric|min:0',
            'products.*.max_discount_amount' => 'nullable|numeric|min:0',
            'products.*.min_quantity'        => 'nullable|integer|min:1',
        ];

        $messages = [
            'products.present'               => 'Debes enviar el campo products (puede ser array vacío para quitar todos).',
            'products.*.id.required'         => 'Cada producto debe tener un id.',
            'products.*.id.exists'           => 'Uno de los productos no existe.',
            'products.*.discount_type.in'    => 'El tipo de descuento debe ser: percentage, fixed_amount o second_unit_percentage.',
            'products.*.discount_value.min'  => 'El valor de descuento debe ser al menos 0.',
            'products.*.min_quantity.min'    => 'La cantidad mínima debe ser al menos 1.',
        ];

        try {
            $validated = $request->validate($rules, $messages);
            $promotion = $this->promotionService->syncProducts($promotion, $validated['products']);
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
            $promotion = $this->promotionService->syncPriceLists($promotion, $validated['price_list_ids']);
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
