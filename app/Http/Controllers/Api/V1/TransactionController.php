<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionRequest;
use App\Models\Asset;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionFormDataService;
use App\Services\UserTransactionQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionFormDataService $transactionFormData,
        private readonly UserTransactionQueryService $userTransactionQuery,
    ) {}

    public function form(Request $request): JsonResponse
    {
        return response()->json($this->transactionFormData->build($request));
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $request->only(['portfolio_id', 'asset_id', 'type', 'from', 'to', 'q']);

        $paginator = $this->userTransactionQuery->filtered($request, $filters)
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => $filters,
            'filter_options' => [
                'portfolios' => $user->portfolios()->orderBy('name')->get(['id', 'name']),
                'assets' => Asset::orderBy('symbol')->get(['id', 'symbol', 'name']),
                'types' => TransactionFormDataService::typesList(),
            ],
        ]);
    }

    public function store(TransactionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['fee_jpy'] = $data['fee_jpy'] ?? 0;

        $transaction = Transaction::create($data);

        return response()->json([
            'transaction' => $transaction->load(['portfolio:id,name', 'asset:id,symbol,name', 'exchange:id,name']),
            'message' => '取引を登録しました。',
        ], 201);
    }

    public function update(TransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        $data = $request->validated();
        $data['fee_jpy'] = $data['fee_jpy'] ?? 0;

        $transaction->update($data);

        return response()->json([
            'transaction' => $transaction->fresh()->load(['portfolio:id,name', 'asset:id,symbol,name', 'exchange:id,name']),
            'message' => '取引を更新しました。',
        ]);
    }

    public function destroy(Transaction $transaction): Response
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->noContent();
    }
}
