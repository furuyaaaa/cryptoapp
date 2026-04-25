<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Asset;
use App\Models\Exchange;
use App\Models\Transaction;
use App\Support\Csv;
use App\Support\LikePattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->only(['portfolio_id', 'asset_id', 'type', 'from', 'to', 'q']);

        $transactions = $this->filteredQuery($request, $filters)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'filterOptions' => [
                'portfolios' => $user->portfolios()->orderBy('name')->get(['id', 'name']),
                'assets' => Asset::orderBy('symbol')->get(['id', 'symbol', 'name']),
                'types' => $this->transactionTypes(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Transactions/Create', $this->formProps($request));
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['fee_jpy'] = $data['fee_jpy'] ?? 0;

        Transaction::create($data);

        return redirect()
            ->route('portfolios.index')
            ->with('success', '取引を登録しました。');
    }

    public function edit(Request $request, Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);

        return Inertia::render('Transactions/Edit', [
            ...$this->formProps($request),
            'transaction' => [
                'id' => $transaction->id,
                'portfolio_id' => $transaction->portfolio_id,
                'asset_id' => $transaction->asset_id,
                'exchange_id' => $transaction->exchange_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'price_jpy' => $transaction->price_jpy,
                'fee_jpy' => $transaction->fee_jpy,
                'executed_at' => $transaction->executed_at->format('Y-m-d\TH:i'),
                'note' => $transaction->note,
            ],
        ]);
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $data = $request->validated();
        $data['fee_jpy'] = $data['fee_jpy'] ?? 0;

        $transaction->update($data);

        return redirect()
            ->route('transactions.index')
            ->with('success', '取引を更新しました。');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return redirect()
            ->back()
            ->with('success', '取引を削除しました。');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->only(['portfolio_id', 'asset_id', 'type', 'from', 'to', 'q']);
        $query = $this->filteredQuery($request, $filters);

        $typeLabels = [
            Transaction::TYPE_BUY => '買い',
            Transaction::TYPE_SELL => '売り',
            Transaction::TYPE_TRANSFER_IN => '入庫',
            Transaction::TYPE_TRANSFER_OUT => '出庫',
        ];

        $filename = 'transactions_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
            'Pragma' => 'no-cache',
        ];

        return response()->stream(function () use ($query, $typeLabels) {
            $handle = fopen('php://output', 'w');

            // Excelで文字化けしないようにUTF-8 BOMを付与
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, Csv::sanitizeRow([
                '取引ID',
                '取引日時',
                '種別',
                '種別コード',
                '銘柄シンボル',
                '銘柄名',
                '数量',
                '単価(JPY)',
                '取引金額(JPY)',
                '手数料(JPY)',
                '合計損益計算用(JPY)',
                '取引所',
                'ポートフォリオ',
                'メモ',
            ]));

            $query->chunk(500, function ($rows) use ($handle, $typeLabels) {
                foreach ($rows as $tx) {
                    $amount = (float) $tx->amount;
                    $price = (float) $tx->price_jpy;
                    $fee = (float) $tx->fee_jpy;
                    $gross = $amount * $price;

                    // 買い/入庫=取得（マイナス）、売り/出庫=譲渡（プラス）として損益計算に使える値
                    $net = match ($tx->type) {
                        Transaction::TYPE_SELL => $gross - $fee,
                        Transaction::TYPE_BUY => -($gross + $fee),
                        default => 0.0,
                    };

                    fputcsv($handle, Csv::sanitizeRow([
                        $tx->id,
                        optional($tx->executed_at)->format('Y-m-d H:i:s'),
                        $typeLabels[$tx->type] ?? $tx->type,
                        $tx->type,
                        $tx->asset?->symbol,
                        $tx->asset?->name,
                        rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.'),
                        number_format($price, 2, '.', ''),
                        number_format($gross, 2, '.', ''),
                        number_format($fee, 2, '.', ''),
                        number_format($net, 2, '.', ''),
                        $tx->exchange?->name,
                        $tx->portfolio?->name,
                        $tx->note,
                    ]));
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    private function filteredQuery(Request $request, array $filters)
    {
        $user = $request->user();

        $query = Transaction::query()
            ->whereHas('portfolio', fn ($q) => $q->where('user_id', $user->id))
            ->with(['portfolio:id,name', 'asset:id,symbol,name,icon_url', 'exchange:id,name'])
            ->orderByDesc('executed_at')
            ->orderByDesc('id');

        if (! empty($filters['portfolio_id'])) {
            $query->where('portfolio_id', $filters['portfolio_id']);
        }
        if (! empty($filters['asset_id'])) {
            $query->where('asset_id', $filters['asset_id']);
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['from'])) {
            $query->where('executed_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('executed_at', '<=', $filters['to'].' 23:59:59');
        }
        if (! empty($filters['q'])) {
            // ユーザー入力の `%` `_` `\` を LIKE のワイルドカードとして解釈させないようエスケープ。
            $like = LikePattern::operator();
            $pattern = LikePattern::contains((string) $filters['q']);
            $query->where(function ($sub) use ($like, $pattern) {
                $sub->where('note', $like, $pattern)
                    ->orWhereHas('asset', fn ($a) => $a->where('symbol', $like, $pattern)->orWhere('name', $like, $pattern));
            });
        }

        return $query;
    }

    private function formProps(Request $request): array
    {
        $user = $request->user();

        $portfolios = $user->portfolios()
            ->orderBy('created_at')
            ->get(['id', 'name']);

        $assets = Asset::query()
            ->with('latestPrice:id,asset_id,price_jpy')
            ->orderBy('symbol')
            ->get(['id', 'symbol', 'name']);

        return [
            'portfolios' => $portfolios,
            'assets' => $assets->map(fn ($a) => [
                'id' => $a->id,
                'symbol' => $a->symbol,
                'name' => $a->name,
                'current_price_jpy' => (float) ($a->latestPrice?->price_jpy ?? 0),
            ]),
            'exchanges' => Exchange::query()->orderBy('id')->get(['id', 'name']),
            'types' => $this->transactionTypes(),
            'defaultPortfolioId' => $portfolios->first()?->id,
        ];
    }

    private function transactionTypes(): array
    {
        return [
            ['value' => Transaction::TYPE_BUY, 'label' => '買い (Buy)'],
            ['value' => Transaction::TYPE_SELL, 'label' => '売り (Sell)'],
            ['value' => Transaction::TYPE_TRANSFER_IN, 'label' => '入庫 (Transfer In)'],
            ['value' => Transaction::TYPE_TRANSFER_OUT, 'label' => '出庫 (Transfer Out)'],
        ];
    }
}
