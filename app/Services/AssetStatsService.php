<?php

namespace App\Services;

use App\Models\AssetPrice;
use Illuminate\Support\Carbon;

class AssetStatsService
{
    /**
     * Compute 24h change and sparkline for a batch of asset IDs.
     *
     * @param  array<int>  $assetIds
     * @param  int  $points  Number of sparkline points to return
     * @return array<int, array{change_24h: ?float, sparkline: array<int, float>, latest_price_jpy: ?float}>
     */
    public function forAssets(array $assetIds, int $points = 24): array
    {
        if ($assetIds === []) {
            return [];
        }

        $since = Carbon::now()->subDay();

        $rows = AssetPrice::query()
            ->whereIn('asset_id', $assetIds)
            ->where('recorded_at', '>=', $since)
            ->orderBy('asset_id')
            ->orderBy('recorded_at')
            ->get(['asset_id', 'price_jpy', 'recorded_at']);

        $byAsset = [];
        foreach ($rows as $row) {
            $byAsset[$row->asset_id][] = (float) $row->price_jpy;
        }

        $result = [];
        foreach ($assetIds as $assetId) {
            $series = $byAsset[$assetId] ?? [];
            $count = count($series);

            if ($count === 0) {
                $result[$assetId] = [
                    'change_24h' => null,
                    'sparkline' => [],
                    'latest_price_jpy' => null,
                ];
                continue;
            }

            $first = $series[0];
            $last = $series[$count - 1];
            $change = $first > 0 ? ($last - $first) / $first : null;

            $sparkline = $this->downsample($series, $points);

            $result[$assetId] = [
                'change_24h' => $change,
                'sparkline' => $sparkline,
                'latest_price_jpy' => $last,
            ];
        }

        return $result;
    }

    /**
     * Downsample a numeric series to at most $points points using bucketed averages.
     *
     * @param  array<int, float>  $series
     * @return array<int, float>
     */
    private function downsample(array $series, int $points): array
    {
        $count = count($series);
        if ($count <= $points) {
            return array_values(array_map(fn ($v) => round($v, 8), $series));
        }

        $result = [];
        for ($i = 0; $i < $points; $i++) {
            $start = (int) floor(($i * $count) / $points);
            $end = (int) floor((($i + 1) * $count) / $points);
            $end = max($end, $start + 1);
            $slice = array_slice($series, $start, $end - $start);
            $result[] = round(array_sum($slice) / count($slice), 8);
        }

        return $result;
    }
}
