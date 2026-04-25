<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\Transaction;
use App\Services\AssetStatsService;
use App\Support\LikePattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(Request $request): Response
    {
        // 多層防御: ルート側の admin ミドルウェアに加えて Policy でも弾く。
        $this->authorize('viewAny', Asset::class);

        $search = trim((string) $request->query('q', ''));

        $query = Asset::query()
            ->withCount('transactions')
            ->with('latestPrice')
            ->orderBy('symbol');

        if ($search !== '') {
            // ユーザー入力に含まれる `%` `_` `\` は LIKE のワイルドカードとして解釈されないよう
            // LikePattern::contains で安全にエスケープしてから連結する。
            $like = LikePattern::operator();
            $symbolPattern = LikePattern::contains(strtoupper($search));
            $namePattern = LikePattern::contains($search);
            $coingeckoPattern = LikePattern::contains(strtolower($search));
            $query->where(function ($q) use ($like, $symbolPattern, $namePattern, $coingeckoPattern) {
                $q->where('symbol', $like, $symbolPattern)
                    ->orWhere('name', $like, $namePattern)
                    ->orWhere('coingecko_id', $like, $coingeckoPattern);
            });
        }

        $assets = $query->paginate(20)->withQueryString()->through(fn (Asset $a) => [
            'id' => $a->id,
            'symbol' => $a->symbol,
            'name' => $a->name,
            'coingecko_id' => $a->coingecko_id,
            'icon_url' => $a->icon_url,
            'transactions_count' => $a->transactions_count,
            'latest_price_jpy' => (float) ($a->latestPrice?->price_jpy ?? 0),
            'latest_price_recorded_at' => $a->latestPrice?->recorded_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ]);

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Asset::class);

        return Inertia::render('Assets/Create');
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        Asset::create($request->validated());

        return redirect()
            ->route('assets.index')
            ->with('success', '銘柄を登録しました。');
    }

    public function edit(Asset $asset): Response
    {
        $this->authorize('update', $asset);

        return Inertia::render('Assets/Edit', [
            'asset' => $asset->only(['id', 'symbol', 'name', 'coingecko_id', 'icon_url']),
        ]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $asset->update($request->validated());

        return redirect()
            ->route('assets.index')
            ->with('success', '銘柄を更新しました。');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        if ($asset->transactions()->exists()) {
            return redirect()
                ->route('assets.index')
                ->with('error', 'この銘柄には取引履歴があるため削除できません。');
        }

        $asset->delete();

        return redirect()
            ->route('assets.index')
            ->with('success', '銘柄を削除しました。');
    }

    public function show(Request $request, AssetStatsService $stats, string $symbol): Response
    {
        $asset = Asset::query()
            ->with('latestPrice')
            ->where('symbol', strtoupper($symbol))
            ->firstOrFail();

        $user = $request->user();
        $portfolioIds = $user->portfolios()->pluck('id');

        $range = (string) $request->query('range', '30d');
        $since = match ($range) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            'all' => null,
            default => now()->subDays(30),
        };

        $pricesQuery = $asset->prices()->orderBy('recorded_at');
        if ($since) {
            $pricesQuery->where('recorded_at', '>=', $since);
        }
        $prices = $pricesQuery->get()->map(fn ($p) => [
            'recorded_at' => $p->recorded_at?->toIso8601String(),
            'price_jpy' => (float) $p->price_jpy,
            'price_usd' => (float) $p->price_usd,
        ]);

        $transactions = Transaction::query()
            ->where('asset_id', $asset->id)
            ->whereIn('portfolio_id', $portfolioIds)
            ->with(['portfolio', 'exchange'])
            ->orderByDesc('executed_at')
            ->get();

        $holding = $this->calculateHolding(
            $transactions,
            (float) ($asset->latestPrice?->price_jpy ?? 0)
        );

        $transactionData = $transactions->map(fn ($tx) => [
            'id' => $tx->id,
            'type' => $tx->type,
            'amount' => (float) $tx->amount,
            'price_jpy' => (float) $tx->price_jpy,
            'fee_jpy' => (float) $tx->fee_jpy,
            'executed_at' => $tx->executed_at?->toIso8601String(),
            'note' => $tx->note,
            'portfolio' => [
                'id' => $tx->portfolio->id,
                'name' => $tx->portfolio->name,
            ],
            'exchange' => $tx->exchange ? [
                'name' => $tx->exchange->name,
            ] : null,
        ]);

        $assetStats = $stats->forAssets([$asset->id])[$asset->id] ?? null;

        return Inertia::render('Assets/Show', [
            'asset' => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'coingecko_id' => $asset->coingecko_id,
                'icon_url' => $asset->icon_url,
                'latest_price_jpy' => (float) ($asset->latestPrice?->price_jpy ?? 0),
                'latest_price_usd' => (float) ($asset->latestPrice?->price_usd ?? 0),
                'latest_price_recorded_at' => $asset->latestPrice?->recorded_at?->toIso8601String(),
                'change_24h' => $assetStats['change_24h'] ?? null,
                'sparkline' => $assetStats['sparkline'] ?? [],
            ],
            'prices' => $prices,
            'range' => $range,
            'holding' => $holding,
            'transactions' => $transactionData,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Transaction>  $transactions
     * @return array<string, mixed>
     */
    private function calculateHolding($transactions, float $currentPrice): array
    {
        $totalInAmount = 0.0;
        $totalInCost = 0.0;
        $totalOutAmount = 0.0;
        $realizedProfit = 0.0;

        foreach ($transactions->sortBy('executed_at') as $tx) {
            $amount = (float) $tx->amount;
            $price = (float) $tx->price_jpy;
            $fee = (float) $tx->fee_jpy;

            if (in_array($tx->type, [Transaction::TYPE_BUY, Transaction::TYPE_TRANSFER_IN], true)) {
                $totalInAmount += $amount;
                $totalInCost += $amount * $price + $fee;
            } else {
                $avgCost = $totalInAmount > 0 ? $totalInCost / $totalInAmount : 0.0;
                if ($tx->type === Transaction::TYPE_SELL) {
                    $realizedProfit += ($price - $avgCost) * $amount - $fee;
                }
                $totalOutAmount += $amount;
            }
        }

        $currentAmount = $totalInAmount - $totalOutAmount;
        $avgBuyPrice = $totalInAmount > 0 ? $totalInCost / $totalInAmount : 0.0;
        $costBasis = $currentAmount * $avgBuyPrice;
        $valuation = $currentAmount * $currentPrice;
        $profit = $valuation - $costBasis;

        return [
            'amount' => $currentAmount,
            'avg_buy_price' => $avgBuyPrice,
            'cost_basis' => $costBasis,
            'valuation' => $valuation,
            'profit' => $profit,
            'profit_rate' => $costBasis > 0 ? $profit / $costBasis : 0,
            'realized_profit' => $realizedProfit,
        ];
    }
}
