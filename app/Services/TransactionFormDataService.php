<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Exchange;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 取引作成・編集フォーム用オプション（Web Inertia / モバイル API 共通）。
 */
final class TransactionFormDataService
{
    /**
     * @return array{
     *     portfolios: \Illuminate\Support\Collection,
     *     assets: \Illuminate\Support\Collection<int, array{id: int, symbol: string, name: string, current_price_jpy: float}>,
     *     exchanges: \Illuminate\Support\Collection,
     *     types: list<array{value: string, label: string}>,
     *     defaultPortfolioId: int|null
     * }
     */
    public function build(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $portfolios = $user->portfolios()
            ->orderBy('created_at')
            ->get(['id', 'name']);

        $assets = Asset::query()
            ->with('latestPrice')
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
            'types' => self::typesList(),
            'defaultPortfolioId' => $portfolios->first()?->id,
        ];
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
