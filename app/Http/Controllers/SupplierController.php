<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierController
{
    protected $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

    public function index()
    {
        try {
            $suppliers = $this->supplierService->get();
            return response()->json($suppliers);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 500);
        }
    }
    public function show(Supplier $supplier)
    {
        try {
            $supplierWithDetail = $this->supplierService->getSupplierById($supplier->id);

            return response()->json($supplierWithDetail);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Categoría no encontrada.'], 404);
        }
    }

    public function store(Request $request)
    {
        $rules = [
            // El nombre debe ser único, ya que es el identificador principal
            'name' => 'required|string|max:255|unique:suppliers,name',
            'contact_name' => 'nullable|string|max:255',
            // El email también debe ser único
            'email' => 'nullable|email|max:255|unique:suppliers,email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:512',
        ];

        $params = [
            'name.required' => 'El nombre del proveedor es obligatorio.',
            'name.unique' => 'Ya existe un proveedor con este nombre.',
            'email.unique' => 'Ya existe un proveedor con este correo electrónico.',
            'email.email' => 'El formato del correo electrónico es inválido.',
        ];

        try {
            $validated = $request->validate($rules, $params);

            // Delegación al servicio
            $newSupplier = $this->supplierService->createSupplier($validated);

            return response()->json($newSupplier, 201); // 201 Created
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el proveedor: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Supplier $supplier)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:suppliers,email,' . $supplier->id,
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:512',
        ];

        try {
            $validated = $request->validate($rules);
            $updatedSupplier = $this->supplierService->updateSupplier($supplier, $validated);
            return response()->json($updatedSupplier, 200);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el proveedor.'], 500);
        }
    }
}
