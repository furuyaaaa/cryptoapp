<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AssetSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetSearchController extends Controller
{
    public function __invoke(Request $request, AssetSearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $rows = $search->searchForPicker($validated['q'] ?? '', $limit);

        return response()->json(['data' => $search->mapToPickerRows($rows)]);
    }
}
