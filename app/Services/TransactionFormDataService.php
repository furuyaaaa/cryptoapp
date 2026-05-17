<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Exchange;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 取引作成・編集フォーム用オプション（Web Inertia / モバイル API 共通）。
 */
final class TransactionFormDataService
{
    /**
     * @return array{
     *     portfolios: Collection,
     *     assets: list<array{id: int, symbol: string, name: string, coingecko_id: ?string, current_price_jpy: float}>,
     *     exchanges: Collection,
     *     types: list<array{value: string, label: string}>,
     *     defaultPortfolioId: int|null
     * }
     */
    public function build(Request $request, ?int $highlightAssetId = null): array
    {
        /** @var User $user */
        $user = $request->user();

        $portfolios = $user->portfolios()
            ->orderBy('created_at')
            ->get(['id', 'name']);

        $initialAssets = [];
        if ($highlightAssetId !== null) {
            $one = Asset::query()
                ->with('latestPrice')
                ->whereKey($highlightAssetId)
                ->first(['id', 'symbol', 'name', 'coingecko_id']);
            if ($one !== null) {
                $initialAssets[] = [
                    'id' => $one->id,
                    'symbol' => $one->symbol,
                    'name' => $one->name,
                    'coingecko_id' => $one->coingecko_id,
                    'current_price_jpy' => (float) ($one->latestPrice?->price_jpy ?? 0),
                ];
            }
        }

        return [
            'portfolios' => $portfolios,
            'assets' => $initialAssets,
            'exchanges' => Exchange::query()->orderBy('id')->get(['id', 'name']),
            'types' => self::typesList(),
            'defaultPortfolioId' => $portfolios->first()?->id,
        ];
    }

    /**
     * 取引一覧の銘柄フィルタ用（自ユーザーの取引で使った銘柄＋現在選択中の asset_id）。
     *
     * @return Collection<int, Asset>
     */
    public static function assetsForTransactionFilters(User $user, ?int $highlightAssetId = null): Collection
    {
        $portfolioIds = $user->portfolios()->pluck('id');
        if ($portfolioIds->isEmpty()) {
            $ids = collect();
        } else {
            $ids = Transaction::query()
                ->whereIn('portfolio_id', $portfolioIds)
                ->distinct()
                ->pluck('asset_id');
        }

        $merged = $ids->merge(collect([$highlightAssetId]))->filter()->unique()->values();
        if ($merged->isEmpty()) {
            return collect();
        }

        return Asset::query()
            ->whereIn('id', $merged)
            ->orderBy('symbol')
            ->orderBy('id')
            ->get(['id', 'symbol', 'name']);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function typesList(): array
    {
        return [
            ['value' => Transaction::TYPE_BUY, 'label' => '買い (Buy)'],
            ['value' => Transaction::TYPE_SELL, 'label' => '売り (Sell)'],
            ['value' => Transaction::TYPE_TRANSFER_IN, 'label' => '入庫 (Transfer In)'],
            ['value' => Transaction::TYPE_TRANSFER_OUT, 'label' => '出庫 (Transfer Out)'],
        ];
    }
}
