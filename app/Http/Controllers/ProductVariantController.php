<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBarcodeVariantRequest;
use App\Http\Requests\StoreVariantRequest;
use App\Http\Requests\UpdateVariantRequest;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductVariantController
{
    public function search(Request $request)
    {
        $q = trim($request->query('q', ''));

        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $variants = ProductVariant::where('sku', 'LIKE', "%{$q}%")
            ->where('is_active', true)
            ->with([
                'product.priceLists',
                'product.suppliers',
                'product.images',
                'attributeValues.categoryAttribute',
                'images',
            ])
            ->get();

        return response()->json($variants);
    }

    public function index(Product $product)
    {
        $variants = $product->variants()
            ->with(['attributeValues.categoryAttribute.options', 'barcodes', 'images'])
            ->get();

        $categoryAttributes = $this->getDeepestCategoryAttributes($product);

        return response()->json([
            'variants'            => $variants,
            'category_attributes' => $categoryAttributes,
        ]);
    }

    public function store(StoreVariantRequest $request, Product $product)
    {
        $validated = $request->validated();

        try {
            $this->validateAttributeValues($product, $validated['attribute_values'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $variant = $product->variants()->create([
            'sku'       => $validated['sku'],
            'stock'     => $validated['stock'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        foreach ($validated['attribute_values'] ?? [] as $av) {
            $variant->attributeValues()->create([
                'category_attribute_id' => $av['category_attribute_id'],
                'value'                 => $av['value'],
            ]);
        }

        return response()->json($variant->load(['attributeValues.categoryAttribute.options', 'barcodes']), 201);
    }

    public function update(UpdateVariantRequest $request, Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['error' => 'La variante no pertenece a este producto.'], 403);
        }

        $validated = $request->validated();

        try {
            $this->validateAttributeValues($product, $validated['attribute_values'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $variant->update([
            'sku'       => $validated['sku'],
            'stock'     => $validated['stock'],
            'is_active' => $validated['is_active'] ?? $variant->is_active,
        ]);

        if (isset($validated['attribute_values'])) {
            $variant->attributeValues()->delete();
            foreach ($validated['attribute_values'] as $av) {
                $variant->attributeValues()->create([
                    'category_attribute_id' => $av['category_attribute_id'],
                    'value'                 => $av['value'],
                ]);
            }
        }

        return response()->json($variant->load(['attributeValues.categoryAttribute.options', 'barcodes']));
    }

    public function storeBarcode(StoreBarcodeVariantRequest $request, Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['error' => 'La variante no pertenece a este producto.'], 403);
        }

        $barcode = ProductBarcode::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'barcode'    => $request->validated()['barcode'],
            'is_primary' => false,
        ]);

        return response()->json($barcode, 201);
    }

    public function destroy(Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['error' => 'La variante no pertenece a este producto.'], 403);
        }

        $variant->delete();
        return response()->json(null, 204);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getDeepestCategoryAttributes(Product $product): array
    {
        $categories = $product->categories()->with('parent')->get();

        if ($categories->isEmpty()) {
            return [];
        }

        $deepest = $categories->sortByDesc(fn($c) => $this->categoryDepth($c))->first();

        return $deepest->attributes()->with('options')->get()->toArray();
    }

    private function categoryDepth(Category $cat, int $depth = 0): int
    {
        return $cat->parent_id ? $this->categoryDepth($cat->parent, $depth + 1) : $depth;
    }

    private function validateAttributeValues(Product $product, array $attributeValues): void
    {
        if (empty($attributeValues)) {
            return;
        }

        $categoryAttributes = $this->getDeepestCategoryAttributes($product);
        $validIds = array_column($categoryAttributes, 'id');
        $requiredIds = array_column(
            array_filter($categoryAttributes, fn($a) => $a['required']),
            'id'
        );

        $submittedIds = array_column($attributeValues, 'category_attribute_id');

        foreach ($submittedIds as $id) {
            if (!in_array($id, $validIds)) {
                throw new \InvalidArgumentException("El atributo ID {$id} no pertenece a la categoría del producto.");
            }
        }

        foreach ($requiredIds as $id) {
            $found = array_filter($attributeValues, fn($av) => $av['category_attribute_id'] == $id && $av['value'] !== '');
            if (empty($found)) {
                $attr = CategoryAttribute::find($id);
                throw new \InvalidArgumentException("El atributo '{$attr->name}' es requerido.");
            }
        }
    }
}
