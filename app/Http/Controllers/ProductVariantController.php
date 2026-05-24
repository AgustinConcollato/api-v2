<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductVariantController
{
    /**
     * Busca variantes por SKU. Devuelve variante + producto (para buscadores).
     */
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

    /**
     * Returns variants + the deepest category's attributes (for the form).
     */
    public function index(Product $product)
    {
        $variants = $product->variants()
            ->with(['attributeValues.categoryAttribute.options', 'barcodes', 'images'])
            ->get();

        $categoryAttributes = $this->getDeepestCategoryAttributes($product);

        return response()->json([
            'variants'           => $variants,
            'category_attributes' => $categoryAttributes,
        ]);
    }

    public function store(Request $request, Product $product)
    {
        try {
            $validated = $request->validate([
                'sku'              => 'required|string|max:100|unique:product_variants,sku',
                'stock'            => 'required|integer|min:0',
                'is_active'        => 'boolean',
                'attribute_values' => 'array',
                'attribute_values.*.category_attribute_id' => 'required|integer|exists:category_attributes,id',
                'attribute_values.*.value'                 => 'required|string|max:255',
            ]);

            $this->validateAttributeValues($product, $validated['attribute_values'] ?? []);

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
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo crear la variante.', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['error' => 'La variante no pertenece a este producto.'], 403);
        }

        try {
            $validated = $request->validate([
                'sku'              => 'required|string|max:100|unique:product_variants,sku,' . $variant->id,
                'stock'            => 'required|integer|min:0',
                'is_active'        => 'boolean',
                'attribute_values' => 'array',
                'attribute_values.*.category_attribute_id' => 'required|integer|exists:category_attributes,id',
                'attribute_values.*.value'                 => 'required|string|max:255',
            ]);

            $this->validateAttributeValues($product, $validated['attribute_values'] ?? []);

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
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo actualizar la variante.', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeBarcode(Request $request, Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['error' => 'La variante no pertenece a este producto.'], 403);
        }

        try {
            $validated = $request->validate([
                'barcode' => 'required|string|max:255|unique:product_barcodes,barcode',
            ], [
                'barcode.required' => 'El código de barras es obligatorio.',
                'barcode.unique'   => 'Este código de barras ya está registrado.',
            ]);

            $barcode = ProductBarcode::create([
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'barcode'    => $validated['barcode'],
                'is_primary' => false,
            ]);

            return response()->json($barcode, 201);
        } catch (ValidationException $e) {
            return response()->json([$e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo agregar el código de barras.'], 500);
        }
    }

    public function destroy(Product $product, ProductVariant $variant)
    {
        if ($variant->product_id !== $product->id) {
            return response()->json(['error' => 'La variante no pertenece a este producto.'], 403);
        }

        try {
            $variant->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo eliminar la variante.', 'message' => $e->getMessage()], 500);
        }
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
