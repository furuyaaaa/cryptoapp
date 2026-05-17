<?php

use App\Models\Asset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('coingecko:sync-assets が coins/list を upsert する', function () {
    Cache::flush();
    Http::fake([
        '*/coins/list*' => Http::response([
            ['id' => 'bitcoin', 'symbol' => 'btc', 'name' => 'Bitcoin'],
            ['id' => 'wrapped-bitcoin', 'symbol' => 'wbtc', 'name' => 'Wrapped Bitcoin'],
        ], 200),
    ]);

    $this->artisan('coingecko:sync-assets')->assertSuccessful();

    $btc = Asset::query()->where('coingecko_id', 'bitcoin')->first();
    expect($btc)->not->toBeNull();
    expect($btc->symbol)->toBe('BTC');
    expect($btc->name)->toBe('Bitcoin');

    $this->assertDatabaseHas('assets', [
        'coingecko_id' => 'wrapped-bitcoin',
        'symbol' => 'WBTC',
    ]);
});

test('coingecko:sync-assets --dry-run は DB を変更しない', function () {
    Cache::flush();
    Http::fake([
        '*/coins/list*' => Http::response([
            ['id' => 'only-dry', 'symbol' => 'dry', 'name' => 'Dry'],
        ], 200),
    ]);

    $before = Asset::count();

    $this->artisan('coingecko:sync-assets', ['--dry-run' => true])->assertSuccessful();

    expect(Asset::count())->toBe($before);
});
