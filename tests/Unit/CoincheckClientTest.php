<?php

use App\Services\Exchanges\CoincheckClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

test('Coincheck private API request is signed with required headers', function () {
    Http::fake([
        'https://coincheck.com/api/exchange/orders/transactions_pagination*' => Http::response([
            'success' => true,
            'pagination' => ['limit' => 50, 'order' => 'desc'],
            'data' => [],
        ], 200),
    ]);

    $client = new CoincheckClient('key', 'secret');
    $client->transactions(50);

    Http::assertSent(function (Request $request) {
        $nonce = $request->header('ACCESS-NONCE')[0] ?? '';
        $url = 'https://coincheck.com/api/exchange/orders/transactions_pagination?limit=50&order=desc';
        $signature = hash_hmac('sha256', $nonce.$url, 'secret');

        return $request->method() === 'GET'
            && $request->url() === $url
            && ($request->header('ACCESS-KEY')[0] ?? '') === 'key'
            && ($request->header('ACCESS-SIGNATURE')[0] ?? '') === $signature;
    });
});

test('Coincheck client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push('rate limited', 429)
        ->push([
            'success' => true,
            'pagination' => ['limit' => 100, 'order' => 'desc'],
            'data' => [
                ['id' => 38, 'pair' => 'btc_jpy'],
            ],
        ], 200);

    $client = new CoincheckClient('key', 'secret');
    $transactions = $client->transactions();

    expect($transactions[0]['id'])->toBe(38);
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('Coincheck client throws API errors', function () {
    Http::fake([
        'https://coincheck.com/*' => Http::response([
            'success' => false,
            'error' => 'invalid authentication',
        ], 200),
    ]);

    $client = new CoincheckClient('key', 'secret');

    expect(fn () => $client->balance())
        ->toThrow(RuntimeException::class, 'Coincheck API error: invalid authentication');
});
