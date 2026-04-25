<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Services\CoinGeckoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class UpdateAssetPrices extends Command
{
    protected $signature = 'prices:update';

    protected $description = 'Fetch latest prices from CoinGecko and store into asset_prices';

    public function handle(CoinGeckoService $coinGecko): int
    {
        $assets = Asset::query()
            ->whereNotNull('coingecko_id')
            ->get(['id', 'symbol', 'coingecko_id', 'icon_url']);

        if ($assets->isEmpty()) {
            $this->warn('No assets with coingecko_id found.');

            return self::SUCCESS;
        }

        $ids = $assets->pluck('coingecko_id')->unique()->values()->all();

        $this->info(sprintf('Fetching prices for %d assets from CoinGecko...', count($ids)));

        try {
            $prices = $coinGecko->fetchPrices($ids);
        } catch (Throwable $e) {
            $this->error('Failed to fetch prices: '.$e->getMessage());

            return self::FAILURE;
        }

        $recordedAt = Carbon::now();
        $created = 0;
        $missing = [];

        foreach ($assets as $asset) {
            if (! isset($prices[$asset->coingecko_id])) {
                $missing[] = $asset->symbol;
                continue;
            }

            AssetPrice::create([
                'asset_id' => $asset->id,
                'price_jpy' => $prices[$asset->coingecko_id]['jpy'],
                'price_usd' => $prices[$asset->coingecko_id]['usd'],
                'recorded_at' => $recordedAt,
            ]);

            $created++;
        }

        $this->info(sprintf('Stored %d price records at %s.', $created, $recordedAt->toDateTimeString()));

        if ($missing !== []) {
            $this->warn('Missing prices for: '.implode(', ', $missing));
        }

        $this->syncIcons($coinGecko, $assets);

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Asset>  $assets
     */
    private function syncIcons(CoinGeckoService $coinGecko, $assets): void
    {
        $needsIcon = $assets->filter(fn ($a) => empty($a->icon_url));

        if ($needsIcon->isEmpty()) {
            return;
        }

        try {
            $markets = $coinGecko->fetchMarkets(
                $needsIcon->pluck('coingecko_id')->unique()->values()->all()
            );
        } catch (Throwable $e) {
            $this->warn('Failed to sync icons: '.$e->getMessage());

            return;
        }

        $updated = 0;
        foreach ($needsIcon as $asset) {
            $image = $markets[$asset->coingecko_id]['image'] ?? null;
            if ($image && $asset->icon_url !== $image) {
                Asset::whereKey($asset->id)->update(['icon_url' => $image]);
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->info(sprintf('Synced icons for %d assets.', $updated));
        }
    }
}
