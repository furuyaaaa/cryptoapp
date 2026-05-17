<?php

namespace App\Services;

use App\Models\Asset;
use App\Support\LikePattern;
use Illuminate\Support\Collection;

/**
 * 取引フォーム等で銘柄を検索する（大量レコード前提）。
 */
final class AssetSearchService
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Asset>
     */
    public function searchForPicker(?string $query, int $limit = 50): Collection
    {
        $q = trim((string) ($query ?? ''));
        $limit = min(max($limit, 1), 100);

        $base = Asset::query()->with('latestPrice')->orderBy('symbol')->orderBy('id');

        if ($q === '') {
            return $base->limit($limit)->get(['id', 'symbol', 'name', 'coingecko_id']);
        }

        $like = LikePattern::operator();
        $symbolPattern = LikePattern::contains(strtoupper($q));
        $namePattern = LikePattern::contains($q);
        $coingeckoPattern = LikePattern::contains(strtolower($q));

        return $base->where(function ($sub) use ($like, $symbolPattern, $namePattern, $coingeckoPattern) {
            $sub->where('symbol', $like, $symbolPattern)
                ->orWhere('name', $like, $namePattern)
                ->orWhere('coingecko_id', $like, $coingeckoPattern);
        })->limit($limit)->get(['id', 'symbol', 'name', 'coingecko_id']);
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return list<array{id: int, symbol: string, name: string, coingecko_id: ?string, current_price_jpy: float}>
     */
    public function mapToPickerRows(Collection $assets): array
    {
        return $assets->map(fn (Asset $a) => [
            'id' => $a->id,
            'symbol' => $a->symbol,
            'name' => $a->name,
            'coingecko_id' => $a->coingecko_id,
            'current_price_jpy' => (float) ($a->latestPrice?->price_jpy ?? 0),
        ])->values()->all();
    }
}
