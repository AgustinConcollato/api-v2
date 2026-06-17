<?php

namespace App\Http\Controllers;

use App\Enums\ProductStatus;
use App\Http\Requests\AddImagesRequest;
use App\Http\Requests\AddPricesRequest;
use App\Http\Requests\DeleteImagesRequest;
use App\Http\Requests\ReorderImagesRequest;
use App\Http\Requests\StoreBarcodeRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\SyncCategoriesRequest;
use App\Http\Requests\UpdateAttributeValuesRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\UpdateProductSuppliersPricesRequest;
use App\Http\Requests\UpdatePricesRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\PublicProductResource;
use App\Models\Product;
use App\Services\DropshippingStockService;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController
{
    public function __construct(private ProductService $productService) {}

    /**
     * Revisa el stock del proveedor (magovirtual) para todos los productos
     * dropshipping y ajusta su disponibilidad. Disparado por un botón del panel.
     */
    public function syncDropshippingStock(DropshippingStockService $service)
    {
        $summary = $service->syncAll();

        return response()->json($summary);
    }

    /**
     * Sincroniza el stock del proveedor para un solo producto dropshipping.
     */
    public function syncDropshippingStockOne(Product $product, DropshippingStockService $service)
    {
        if (!$product->is_dropshipping) {
            return response()->json(['error' => 'El producto no es de dropshipping.'], 422);
        }

        $summary = $service->syncProduct($product);

        return response()->json($summary);
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = ProductStatus::Incomplete;
        $validated['stock'] ??= 0;

        $product = DB::transaction(function () use ($validated, $request) {
            $product = $this->productService->createProduct($validated);

            if (isset($validated['categories'])) {
                $this->productService->syncCategories($product, $validated['categories']);
            }

            if ($request->hasFile('images')) {
                $files = $request->file('images');
                $positions = $validated['image_positions'] ?? [];
                $this->productService->processAndAttachImages($product, $files, $positions);
            }

            if (!empty($validated['attribute_values'])) {
                foreach ($validated['attribute_values'] as $av) {
                    $product->attributeValues()->create([
                        'category_attribute_id' => $av['category_attribute_id'],
                        'value'                 => $av['value'],
                    ]);
                }
            }

            return $product;
        });

        $product->load('images');

        return response()->json(new ProductResource($product), 201);
    }

    public function addPrices(AddPricesRequest $request, Product $product)
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($validated, $product) {
            if (isset($validated['suppliers'])) {
                $this->productService->syncSuppliers($product, $validated['suppliers']);
            }

            if (isset($validated['price_lists'])) {
                $this->productService->syncPriceLists($product, $validated['price_lists']);
            }

            return $product;
        });

        $product->update(['status' => $this->productService->computeStatus($product)]);

        return response()->json(new ProductResource($product), 201);
    }

    public function show(Product $product)
    {
        $product->load(
            'attributeValues',
            'images',
            'suppliers',
            'barcodes',
            'priceLists',
            'promotions',
            'categories'
        );
        $product->loadCount('variants');

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product = $this->productService->updateProduct($product, $request->validated());

        return response()->json(new ProductResource($product), 200);
    }

    public function syncCategories(SyncCategoriesRequest $request, Product $product)
    {
        $productWithCategories = $this->productService->syncCategories($product, $request->validated()['categories']);

        return response()->json(new ProductResource($productWithCategories), 200);
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'search',
            'category_id',
            'supplier_id',
            'stock_min',
            'stock_max',
            'price_min',
            'price_max',
            'price_list_id',
            'sort_by',
            'sort_order',
            'per_page',
            'status'
        ]);

        $products = $this->productService->getFilteredProducts($filters);

        return response()->json($products);
    }

    public function publicShow(Request $request, Product $product)
    {
        if ($product->status !== ProductStatus::Published) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $priceListId = $request->query('price_list_id') ? (int) $request->query('price_list_id') : null;

        $product->load([
            'images',
            'categories.parent',
            'priceLists' => fn($q) => $priceListId ? $q->where('price_list_id', $priceListId) : $q,
            'barcodes',
            'attributeValues.categoryAttribute',
            'variants' => fn($q) => $q->where('is_active', true)->orderBy('id'),
            'variants.attributeValues.categoryAttribute',
            'variants.images',
            'variants.barcodes',
            'promotions' => fn($q) => $q->active(),
            'promotions.priceLists',
        ]);

        $product->setRelation('priceLists', $product->priceLists->map(fn($pl) => [
            'id'    => $pl->id,
            'price' => $pl->pivot->price,
        ]));

        $promotions = $priceListId
            ? $product->promotions->filter(
                fn($p) =>
                $p->priceLists->isEmpty() || $p->priceLists->pluck('id')->contains($priceListId)
            )
            : $product->promotions;

        $product->setRelation('promotions', $promotions->map(function ($p) {
            $cond = $p->getEffectiveConditions($p->pivot);
            return [
                'discount_type'       => $cond['discount_type'],
                'discount_value'      => (float) $cond['discount_value'],
                'max_discount_amount' => $cond['max_discount_amount'] !== null ? (float) $cond['max_discount_amount'] : null,
                'min_quantity'        => $cond['min_quantity'],
                'ends_at'             => $p->ends_at,
                'price_list_ids'      => $p->priceLists->pluck('id')->toArray(),
            ];
        })->values());

        return new PublicProductResource($product);
    }

    public function publicIndex(Request $request)
    {
        $filters = $request->only([
            'search',
            'category_id',
            'stock_min',
            'stock_max',
            'price_min',
            'price_max',
            'price_list_id',
            'sort_by',
            'sort_order',
            'per_page'
        ]);

        $products = $this->productService->getPublicProducts($filters);

        return response()->json($products);
    }

    public function publicNewArrivals(Request $request)
    {
        $priceListId = $request->query('price_list_id') ? (int) $request->query('price_list_id') : null;

        $products = Product::published()
            ->where(function ($q) {
                $q->where('stock', '>', 0)
                  ->orWhereHas('variants', fn($vq) => $vq->where('is_active', true)->where('stock', '>', 0));
            })
            ->with($this->publicEagerLoads($priceListId))
            ->orderByStockEntry('desc')
            ->limit(12)
            ->get();

        return response()->json(PublicProductResource::collection($products));
    }

    public function publicBestSellers(Request $request)
    {
        $priceListId = $request->query('price_list_id') ? (int) $request->query('price_list_id') : null;

        $products = Product::published()
            ->where(function ($q) {
                $q->where('stock', '>', 0)
                  ->orWhereHas('variants', fn($vq) => $vq->where('is_active', true)->where('stock', '>', 0));
            })
            ->with($this->publicEagerLoads($priceListId))
            ->addSelect([
                'sold_qty' => DB::table('order_details')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('order_details.product_id', 'products.id'),
            ])
            ->orderBy('sold_qty', 'desc')
            ->limit(12)
            ->get();

        return response()->json(PublicProductResource::collection($products));
    }

    private function publicEagerLoads(?int $priceListId): array
    {
        return [
            'images',
            'categories.parent',
            'barcodes',
            'attributeValues',
            'priceLists' => fn($q) => $priceListId ? $q->where('price_list_id', $priceListId) : $q,
            'promotions' => fn($q) => $q->active(),
            'promotions.priceLists',
            'variants' => fn($q) => $q->where('is_active', true)->orderBy('id'),
            'variants.attributeValues.categoryAttribute',
            'variants.images',
        ];
    }

    public function storeBarcode(StoreBarcodeRequest $request, Product $product)
    {
        $validated = $request->validated();

        $this->productService->associateBarcode(
            $product,
            $validated['barcode'],
            $validated['is_primary'] ?? false,
        );

        $product->barcodes;

        return response()->json(new ProductResource($product), 201);
    }

    public function destroyBarcode(int $barcodeId)
    {
        $result = $this->productService->dissociateBarcode($barcodeId);

        if ($result === null) {
            return response()->json([
                'error' => "El código de barras con ID {$barcodeId} no fue encontrado."
            ], 404);
        }

        if ($result) {
            return response()->json(['message' => 'Código de barras eliminado correctamente.']);
        }

        return response()->json(['error' => 'No se pudo eliminar el código de barras debido a un error interno.'], 500);
    }

    public function getByBarcode(string $barcode)
    {
        $product = $this->productService->getProductByBarcode($barcode);
        return response()->json(new ProductResource($product), 200);
    }

    public function updatePrices(UpdatePricesRequest $request, Product $product)
    {
        $product = $this->productService->syncPricesAndSuppliers(
            $product,
            $request->validated()['price_lists'],
        );

        return response()->json(new ProductResource($product), 200);
    }

    public function addImages(AddImagesRequest $request, Product $product)
    {
        $files = $request->file('images');
        $variantId = $request->input('variant_id') ? (int) $request->input('variant_id') : null;

        DB::transaction(function () use ($product, $files, $variantId) {
            $this->productService->addImagesToTheEnd($product, $files, $variantId);
        });

        if ($variantId) {
            $variant = $product->variants()->with('images')->find($variantId);
            return response()->json(['images' => $variant?->images ?? []], 201);
        }

        $product->load('images');
        return response()->json(new ProductResource($product), 201);
    }

    public function deleteImages(DeleteImagesRequest $request, Product $product)
    {
        DB::transaction(function () use ($product, $request) {
            $this->productService->deleteImages($product, $request->validated()['image_ids']);
        });

        return response()->json(['message' => 'Imágenes eliminadas y reordenadas correctamente.'], 200);
    }

    public function reorderImages(ReorderImagesRequest $request, Product $product)
    {
        $validated = $request->validated();

        $imagePositions = [];
        foreach ($validated['positions'] as $item) {
            $imagePositions[$item['id']] = $item['position'];
        }

        DB::transaction(function () use ($product, $imagePositions) {
            $this->productService->reorderImages($product, $imagePositions);
        });

        $product->images;

        return response()->json(new ProductResource($product), 200);
    }

    public function updateProductSuppliersPrices(UpdateProductSuppliersPricesRequest $request, Product $product)
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($validated, $product) {
            $suppliers = $validated['suppliers'] ?? [];
            $this->productService->syncSuppliers($product, $suppliers);

            if (isset($validated['price_lists'])) {
                $this->productService->syncPriceLists($product, $validated['price_lists']);
            }

            return $product;
        });

        $product->update(['status' => $this->productService->computeStatus($product)]);
        $product->load('suppliers', 'priceLists');

        return response()->json(new ProductResource($product), 200);
    }

    public function updateAttributeValues(UpdateAttributeValuesRequest $request, Product $product)
    {
        $validated = $request->validated();

        foreach ($validated['attribute_values'] as $av) {
            $product->attributeValues()->updateOrCreate(
                ['category_attribute_id' => $av['category_attribute_id']],
                ['value' => $av['value']]
            );
        }

        return response()->json($product->attributeValues()->with('categoryAttribute.options')->get());
    }

    public function updateStatus(UpdateStatusRequest $request, Product $product)
    {
        $validated = $request->validated();

        $newStatus = $validated['status'] === 'archived'
            ? ProductStatus::Archived
            : $this->productService->computeStatus($product);

        $product = $this->productService->updateProductStatus($product, $newStatus->value);

        return response()->json(new ProductResource($product), 200);
    }
}
