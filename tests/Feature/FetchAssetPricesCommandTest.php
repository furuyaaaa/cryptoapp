<?php

use App\Models\Asset;
use App\Models\AssetPrice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('coingecko:fetch-asset-prices が simple/price を asset_prices に保存する', function () {
    Cache::flush();
    Http::fake([
        '*/simple/price*' => Http::response([
            'bitcoin' => ['jpy' => 1_000_000, 'usd' => 60_000],
        ], 200),
    ]);

    $asset = Asset::factory()->create([
        'coingecko_id' => 'bitcoin',
        'icon_url' => 'https://example.com/btc.png',
    ]);

    $this->artisan('coingecko:fetch-asset-prices')->assertSuccessful();

    expect(AssetPrice::query()->where('asset_id', $asset->id)->count())->toBe(1);
    $row = AssetPrice::query()->where('asset_id', $asset->id)->first();
    expect((float) $row->price_jpy)->toBe(1_000_000.0);
    expect((float) $row->price_usd)->toBe(60_000.0);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'simple/price')
            && str_contains($request->url(), 'bitcoin')
            && str_contains($request->url(), 'vs_currencies');
    });
});

test('coingecko:fetch-asset-prices は icon 未設定時に coins/markets も叩く', function () {
    Cache::flush();
    Http::fake([
        '*/simple/price*' => Http::response([
            'ethereum' => ['jpy' => 500_000, 'usd' => 3_000],
        ], 200),
        '*/coins/markets*' => Http::response([
            ['id' => 'ethereum', 'image' => 'https://example.com/eth.png'],
        ], 200),
    ]);

    $asset = Asset::factory()->create([
        'coingecko_id' => 'ethereum',
        'icon_url' => null,
    ]);

    $this->artisan('coingecko:fetch-asset-prices')->assertSuccessful();

    $asset->refresh();
    expect($asset->icon_url)->toBe('https://example.com/eth.png');
    Http::assertSentCount(2);
});
