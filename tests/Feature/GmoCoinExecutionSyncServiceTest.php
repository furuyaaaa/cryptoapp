<?php

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\GmoCoinExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('GMO Coin sync imports spot executions and skips duplicates', function () {
    Http::fake([
        'https://api.coin.z.com/private/v1/latestExecutions*' => Http::response([
            'status' => 0,
            'data' => [
                'list' => [
                    [
                        'executionId' => 12344,
                        'symbol' => 'BTC',
                        'side' => 'BUY',
                        'size' => '0.02',
                        'price' => '9000000',
                        'fee' => '-10',
                        'timestamp' => '2025-12-30T00:00:00.000Z',
                    ],
                    [
                        'executionId' => 12345,
                        'symbol' => 'BTC',
                        'side' => 'BUY',
                        'size' => '0.01',
                        'price' => '10000000',
                        'fee' => '-15',
                        'timestamp' => '2026-01-01T00:00:00.000Z',
                    ],
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);
    $exchange = Exchange::create(['code' => 'gmo_coin', 'name' => 'GMOコイン', 'country' => 'JP']);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'GMOコイン BTC',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'product_code' => 'BTC',
        'sync_start_at' => '2026-01-01 00:00:00',
        'is_active' => true,
    ]);

    $service = app(GmoCoinExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 2, 'imported' => 1, 'skipped' => 1]);
    expect($second)->toMatchArray(['fetched' => 2, 'imported' => 0, 'skipped' => 2]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('gmo_coin:latest_executions')
        ->and($transaction->external_id)->toBe('BTC:12345')
        ->and((float) $transaction->amount)->toBe(0.01)
        ->and((float) $transaction->price_jpy)->toBe(10000000.0)
        ->and((float) $transaction->fee_jpy)->toBe(15.0);
});
