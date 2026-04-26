<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PortfolioRequest;
use App\Models\Portfolio;
use App\Services\PortfolioListDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioListDataService $portfolioListData) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->portfolioListData->buildForUser($request->user());

        return response()->json($data);
    }

    public function store(PortfolioRequest $request): JsonResponse
    {
        $portfolio = $request->user()->portfolios()->create($request->validated());

        return response()->json([
            'portfolio' => $portfolio->only(['id', 'name', 'description', 'created_at', 'updated_at']),
            'message' => 'ポートフォリオを作成しました。',
        ], 201);
    }

    public function update(PortfolioRequest $request, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->update($request->validated());

        return response()->json([
            'portfolio' => $portfolio->only(['id', 'name', 'description', 'created_at', 'updated_at']),
            'message' => 'ポートフォリオを更新しました。',
        ]);
    }

    public function destroy(Portfolio $portfolio): Response
    {
        $this->authorize('delete', $portfolio);

        $portfolio->delete();

        return response()->noContent();
    }
}
