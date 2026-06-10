<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexPromotionRequest;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\SyncPriceListsRequest;
use App\Http\Requests\SyncProductsRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\PromotionService;

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
}
