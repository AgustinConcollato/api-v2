<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;

class SupplierController
{
    public function __construct(private SupplierService $supplierService) {}

    public function index()
    {
        $suppliers = $this->supplierService->get();
        return response()->json($suppliers);
    }

    public function show(Supplier $supplier)
    {
        $supplierWithDetail = $this->supplierService->getSupplierById($supplier->id);

        return response()->json($supplierWithDetail);
    }

    public function store(StoreSupplierRequest $request)
    {
        $newSupplier = $this->supplierService->createSupplier($request->validated());

        return response()->json($newSupplier, 201);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $updatedSupplier = $this->supplierService->updateSupplier($supplier, $request->validated());

        return response()->json($updatedSupplier, 200);
    }
}
