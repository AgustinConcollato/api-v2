<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPromotionRequest;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\SyncPriceListsRequest;
use App\Http\Requests\SyncProductsRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Http\Resources\PublicProductResource;
use App\Models\Promotion;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class PromotionController
{
    public function __construct(private PromotionService $promotionService) {}

    public function index(IndexPromotionRequest $request)
    {
        $promotions = $this->promotionService->index($request->validated());

        return response()->json($promotions);
    }

    public function store(StorePromotionRequest $request)
    {
        try {
            $promotion = $this->promotionService->store($request->validated());

            return response()->json(new PromotionResource($promotion), 201);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json([
                'error' => 'Uno o más productos ya pertenecen a otra promoción. Cada producto solo puede estar en una promoción.',
            ], 422);
        }
    }

    public function show(Promotion $promotion)
    {
        return new PromotionResource($this->promotionService->show($promotion));
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion)
    {
        try {
            $promotion = $this->promotionService->update($promotion, $request->validated());

            return response()->json(new PromotionResource($promotion));
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json([
                'error' => 'Uno o más productos ya pertenecen a otra promoción. Cada producto solo puede estar en una promoción.',
            ], 422);
        }
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();
        return response()->json(['message' => 'Promoción eliminada correctamente.'], 200);
    }

    public function syncProducts(SyncProductsRequest $request, Promotion $promotion)
    {
        try {
            $promotion = $this->promotionService->syncProducts($promotion, $request->validated()['products']);
            return response()->json(new PromotionResource($promotion));
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json([
                'error' => 'Uno o más productos ya pertenecen a otra promoción. Cada producto solo puede estar en una promoción.',
            ], 422);
        }
    }

    public function syncPriceLists(SyncPriceListsRequest $request, Promotion $promotion)
    {
        $promotion = $this->promotionService->syncPriceLists($promotion, $request->validated()['price_list_ids']);
        return response()->json(new PromotionResource($promotion));
    }

    public function publicShow(Request $request, Promotion $promotion)
    {
        $now = now();
        $isVisible = $promotion->is_active
            && $promotion->show_on_web
            && ($promotion->starts_at === null || $promotion->starts_at <= $now)
            && ($promotion->ends_at === null || $promotion->ends_at >= $now);

        if (!$isVisible) {
            return response()->json(['message' => 'Promoción no encontrada'], 404);
        }

        $priceListId = $request->query('price_list_id') ? (int) $request->query('price_list_id') : null;

        $promotion->load([
            'priceLists:id,name',
            'products' => fn($q) => $q->published()->with([
                'images',
                'priceLists',
                'promotions' => fn($pq) => $pq->active(),
                'promotions.priceLists',
                'variants' => fn($vq) => $vq->where('is_active', true)->with('images', 'attributeValues.categoryAttribute'),
            ]),
        ]);

        if ($priceListId) {
            $listIds = $promotion->priceLists->pluck('id');
            if ($listIds->isNotEmpty() && !$listIds->contains($priceListId)) {
                return response()->json(['message' => 'Promoción no encontrada'], 404);
            }
        }

        $inStock = $promotion->products->filter(function ($product) {
            return $product->stock > 0
                || $product->variants->contains(fn($v) => $v->stock > 0);
        })->values();

        $promotion->setRelation('products', $inStock);

        return response()->json([
            'id'                 => $promotion->id,
            'name'               => $promotion->name,
            'description'        => $promotion->description,
            'discount_type'      => $promotion->discount_type,
            'discount_value'     => (float) $promotion->discount_value,
            'max_discount_amount' => $promotion->max_discount_amount !== null ? (float) $promotion->max_discount_amount : null,
            'min_quantity'       => $promotion->min_quantity,
            'ends_at'            => $promotion->ends_at,
            'price_list_ids'     => $promotion->priceLists->pluck('id')->toArray(),
            'products'           => $promotion->products->map(function ($product) use ($priceListId) {
                $priceLists = $priceListId
                    ? $product->priceLists->where('id', $priceListId)
                    : $product->priceLists;

                $promotions = $priceListId
                    ? $product->promotions->filter(
                        fn($p) => $p->priceLists->isEmpty() || $p->priceLists->pluck('id')->contains($priceListId)
                    )
                    : $product->promotions;

                return [
                    'id'             => $product->id,
                    'name'           => $product->name,
                    'sku'            => $product->sku,
                    'stock'          => $product->stock,
                    'is_dropshipping' => $product->is_dropshipping,
                    'images'         => $product->images->map(fn($img) => [
                        'thumbnail_path' => $img->thumbnail_path,
                        'position'       => $img->position,
                    ])->sortBy('position')->values(),
                    'price_lists'    => $priceLists->map(fn($pl) => [
                        'id'    => $pl->id,
                        'price' => $pl->pivot->price,
                    ])->values(),
                    'promotions'     => $promotions->map(function ($p) {
                        $cond = $p->getEffectiveConditions($p->pivot);
                        return [
                            'discount_type'       => $cond['discount_type'],
                            'discount_value'      => (float) $cond['discount_value'],
                            'max_discount_amount' => $cond['max_discount_amount'] !== null ? (float) $cond['max_discount_amount'] : null,
                            'min_quantity'        => $cond['min_quantity'],
                            'ends_at'             => $p->ends_at,
                            'price_list_ids'      => $p->priceLists->pluck('id')->toArray(),
                        ];
                    })->values(),
                    'variants'       => $product->variants->map(fn($v) => [
                        'id'        => $v->id,
                        'sku'       => $v->sku,
                        'stock'     => $v->stock,
                        'is_active' => $v->is_active,
                        'images'    => $v->images->map(fn($img) => [
                            'thumbnail_path' => $img->thumbnail_path,
                            'position'       => $img->position,
                        ])->sortBy('position')->values(),
                        'attribute_values' => $v->attributeValues->map(fn($av) => [
                            'category_attribute_id' => $av->category_attribute_id,
                            'value'                 => $av->value,
                            'category_attribute'    => $av->categoryAttribute ? [
                                'name' => $av->categoryAttribute->name,
                            ] : null,
                        ])->values(),
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    public function publicIndex(Request $request)
    {
        $priceListId = $request->query('price_list_id') ? (int) $request->query('price_list_id') : null;

        $promotions = $this->promotionService->publicIndex($priceListId);

        return response()->json($promotions->map(function ($promo) use ($priceListId) {
            return [
                'id'           => $promo->id,
                'name'         => $promo->name,
                'description'  => $promo->description,
                'discount_type'  => $promo->discount_type,
                'discount_value' => (float) $promo->discount_value,
                'max_discount_amount' => $promo->max_discount_amount !== null ? (float) $promo->max_discount_amount : null,
                'min_quantity'   => $promo->min_quantity,
                'ends_at'        => $promo->ends_at,
                'price_list_ids' => $promo->priceLists->pluck('id')->toArray(),
                'products'       => $promo->products->map(function ($product) use ($priceListId) {
                    $priceLists = $priceListId
                        ? $product->priceLists->where('id', $priceListId)
                        : $product->priceLists;

                    $promotions = $priceListId
                        ? $product->promotions->filter(
                            fn($p) => $p->priceLists->isEmpty() || $p->priceLists->pluck('id')->contains($priceListId)
                        )
                        : $product->promotions;

                    return [
                        'id'     => $product->id,
                        'name'   => $product->name,
                        'sku'    => $product->sku,
                        'stock'  => $product->stock,
                        'is_dropshipping' => $product->is_dropshipping,
                        'images' => $product->images->map(fn($img) => [
                            'thumbnail_path' => $img->thumbnail_path,
                            'position'       => $img->position,
                        ])->sortBy('position')->values(),
                        'price_lists' => $priceLists->map(fn($pl) => [
                            'id'    => $pl->id,
                            'price' => $pl->pivot->price,
                        ])->values(),
                        'promotions' => $promotions->map(function ($p) use ($product) {
                            $cond = $p->getEffectiveConditions($p->pivot);
                            return [
                                'discount_type'       => $cond['discount_type'],
                                'discount_value'      => (float) $cond['discount_value'],
                                'max_discount_amount' => $cond['max_discount_amount'] !== null ? (float) $cond['max_discount_amount'] : null,
                                'min_quantity'        => $cond['min_quantity'],
                                'ends_at'             => $p->ends_at,
                                'price_list_ids'      => $p->priceLists->pluck('id')->toArray(),
                            ];
                        })->values(),
                        'variants' => $product->variants->map(fn($v) => [
                            'id'        => $v->id,
                            'sku'       => $v->sku,
                            'stock'     => $v->stock,
                            'is_active' => $v->is_active,
                            'images' => $v->images->map(fn($img) => [
                                'thumbnail_path' => $img->thumbnail_path,
                                'position'       => $img->position,
                            ])->sortBy('position')->values(),
                            'attribute_values' => $v->attributeValues->map(fn($av) => [
                                'category_attribute_id' => $av->category_attribute_id,
                                'value' => $av->value,
                                'category_attribute' => $av->categoryAttribute ? [
                                    'name' => $av->categoryAttribute->name,
                                ] : null,
                            ])->values(),
                        ])->values(),
                    ];
                })->values(),
            ];
        })->values());
    }
}
