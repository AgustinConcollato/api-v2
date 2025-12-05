<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class SupplierService
{

    /**
     * Summary of get
     * @return Collection<int, Supplier>
     */
    public function get(): Collection
    {
        return Supplier::get();
    }

    /**
     * Summary of getSupplierById
     * @param int $id
     * @return Supplier
     */
    public function getSupplierById(int $id): Supplier
    {
        $supplier = Supplier::findOrFail($id);
        return $supplier;
    }

    /** Crea un nuevo registro de proveedor.
     * @param array $data Los datos validados (name, email, etc.).
     * @return Supplier
     */
    public function createSupplier(array $data): Supplier
    {
        // Asumiendo que tienes el modelo Supplier importado y configurado
        return Supplier::create($data);
    }

    public function updateSupplier(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier;
    }
}
