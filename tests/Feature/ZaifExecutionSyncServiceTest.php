<?php

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Exchanges\ZaifExecutionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('Zaif sync imports JPY spot trades and skips old executions and duplicates', function () {
    Http::fake([
        'https://api.zaif.jp/api/1/currency_pairs/all' => Http::response([
            ['currency_pair' => 'btc_jpy', 'is_token' => false],
            ['currency_pair' => 'mona_jpy', 'is_token' => true],
            ['currency_pair' => 'eth_btc', 'is_token' => false],
        ], 200),
        'https://api.zaif.jp/tapi' => Http::response([
            'success' => 1,
            'return' => [
                '181' => [
                    'currency_pair' => 'btc_jpy',
                    'your_action' => 'bid',
                    'amount' => 0.02,
                    'price' => 9000000,
                    'fee_amount' => 10,
                    'timestamp' => 1767139200,
                ],
                '182' => [
                    'currency_pair' => 'btc_jpy',
                    'your_action' => 'bid',
                    'amount' => 0.01,
                    'price' => 10000000,
                    'fee_amount' => 15,
                    'timestamp' => 1767225600,
                ],
            ],
        ], 200),
    ]);

    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->create(['user_id' => $user->id]);
    $exchange = Exchange::create(['code' => 'zaif', 'name' => 'Zaif', 'country' => 'JP']);
    $connection = ExchangeConnection::create([
        'user_id' => $user->id,
        'exchange_id' => $exchange->id,
        'portfolio_id' => $portfolio->id,
        'label' => 'Zaif 全JPY建て現物',
        'api_key' => 'key',
        'api_secret' => 'secret',
        'product_code' => ZaifExecutionSyncService::ALL_JPY_PAIRS,
        'sync_start_at' => '2026-01-01 00:00:00',
        'is_active' => true,
    ]);

    $service = app(ZaifExecutionSyncService::class);

    $first = $service->sync($connection);
    $second = $service->sync($connection->refresh());

    expect($first)->toMatchArray(['fetched' => 2, 'imported' => 1, 'skipped' => 1]);
    expect($second)->toMatchArray(['fetched' => 2, 'imported' => 0, 'skipped' => 2]);

    $transaction = Transaction::first();
    expect(Transaction::count())->toBe(1)
        ->and($transaction->external_source)->toBe('zaif:trade_history')
        ->and($transaction->external_id)->toBe('btc_jpy:182')
        ->and((float) $transaction->amount)->toBe(0.01)
        ->and((float) $transaction->price_jpy)->toBe(10000000.0)
        ->and((float) $transaction->fee_jpy)->toBe(15.0);
});
