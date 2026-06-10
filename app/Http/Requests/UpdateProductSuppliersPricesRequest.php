<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductSuppliersPricesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'suppliers'                        => 'array',
            'suppliers.*.supplier_id'          => 'required|uuid|exists:suppliers,id',
            'suppliers.*.purchase_price'       => 'required|numeric|min:0',
            'suppliers.*.freight_percent'      => 'nullable|numeric|min:0|max:100',
            'suppliers.*.supplier_product_url' => 'nullable|url|max:512',
            'price_lists'                      => 'nullable|array',
            'price_lists.*.list_id'            => 'required|exists:price_lists,id',
            'price_lists.*.price'              => 'required|numeric|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'suppliers.*.supplier_id.exists'        => 'El ID de uno de los proveedores no es válido.',
            'suppliers.*.purchase_price.required'   => 'El precio de compra es obligatorio para cada proveedor.',
            'suppliers.*.purchase_price.numeric'    => 'El precio de compra debe ser un número.',
            'suppliers.*.supplier_product_url.url'  => 'La URL del producto del proveedor debe ser un formato válido.',
            'price_lists.*.list_id.exists'          => 'El ID de uno de los proveedores no es válido.',
            'price_lists.*.price.required'          => 'El precio es obligatorio para cada lista de precio.',
            'price_lists.*.price.numeric'           => 'El precio de venta debe ser un número.',
            'price_lists.*.price.min'               => 'El precio debe ser mayor a 0.',
        ];
    }
}
