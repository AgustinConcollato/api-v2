<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompareMonthsRequest;
use App\Http\Requests\OverviewRequest;
use App\Services\AnalyticsService;

class AnalyticsController
{
    public function __construct(private AnalyticsService $analyticsService) {}

    public function overview(OverviewRequest $request)
    {
        $data = $this->analyticsService->getOverview($request->validated());
        return response()->json($data);
    }

    public function compareMonths(CompareMonthsRequest $request)
    {
        $data = $this->analyticsService->compareMonths($request->validated());
        return response()->json($data);
    }
}
