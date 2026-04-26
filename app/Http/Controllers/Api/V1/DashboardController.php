<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardDataService $dashboardData) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->dashboardData->buildForUser($request->user());

        return response()->json([
            'totals' => $payload['totals'],
            'allocation' => $payload['allocation'],
            'topHoldings' => $payload['topHoldings'],
            'recentTransactions' => $payload['recentTransactions']->values(),
        ]);
    }
}
