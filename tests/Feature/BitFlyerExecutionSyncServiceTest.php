<?php

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\BitFlyerExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('bitFlyer sync skips executions before sync start date and duplicates', function () {
    Http::fake([
        'https://api.bitflyer.com/v1/me/getexecutions*' => Http::response([
            [
                'id' => 101,
                'side' => 'BUY',
                'size' => 0.01,
                'price' => 10000000,
                'commission' => 0.000001,
                'exec_date' => '2025-12-30T00:00:00.000',
            ],
            [
                'id' => 102,
                'side' => 'BUY',
                'size' => 0.02,
                'price' => 11000000,
                'commission' => 0.000002,
                'exec_date' => '2026-01-02T00:00:00.000',
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);
    $exchange = Exchange::create(['code' => 'bitflyer', 'name' => 'bitFlyer', 'country' => 'JP']);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'bitFlyer BTC_JPY',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'product_code' => 'BTC_JPY',
        'sync_start_at' => '2026-01-01 00:00:00',
        'is_active' => true,
    ]);

    $service = app(BitFlyerExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 2, 'imported' => 1, 'skipped' => 1]);
    expect($second)->toMatchArray(['fetched' => 2, 'imported' => 0, 'skipped' => 2]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('bitflyer:getexecutions')
        ->and($transaction->external_id)->toBe('BTC_JPY:102')
        ->and((float) $transaction->amount)->toBe(0.02)
        ->and((float) $transaction->price_jpy)->toBe(11000000.0);
});
