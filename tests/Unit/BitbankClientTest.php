<?php

use App\Services\Exchanges\BitbankClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

test('bitbank private API request is signed with required headers', function () {
    Http::fake([
        'https://api.bitbank.cc/v1/user/spot/trade_history*' => Http::response([
            'success' => 1,
            'data' => ['trades' => []],
        ], 200),
    ]);

    $client = new BitbankClient('key', 'secret');
    $client->tradeHistory('btc_jpy', 50);

    Http::assertSent(function (Request $request) {
        $nonce = $request->header('ACCESS-NONCE')[0] ?? '';
        $signature = hash_hmac(
            'sha256',
            $nonce.'/v1/user/spot/trade_history?pair=btc_jpy&count=50&order=desc',
            'secret',
        );

        return $request->method() === 'GET'
            && $request->url() === 'https://api.bitbank.cc/v1/user/spot/trade_history?pair=btc_jpy&count=50&order=desc'
            && $request->header('ACCESS-KEY')[0] === 'key'
            && $request->header('ACCESS-SIGNATURE')[0] === $signature;
    });
});

test('bitbank pair list uses public API without private headers', function () {
    Http::fake([
        'https://api.bitbank.cc/v1/spot/pairs' => Http::response([
            'success' => 1,
            'data' => [
                'pairs' => [
                    ['name' => 'btc_jpy', 'quote_asset' => 'jpy', 'is_enabled' => true],
                    ['name' => 'eth_jpy', 'quote_asset' => 'jpy', 'is_enabled' => true],
                ],
            ],
        ], 200),
    ]);

    $client = new BitbankClient('key', 'secret');
    $pairs = $client->pairs();

    expect($pairs)->toHaveCount(2);
    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.bitbank.cc/v1/spot/pairs'
            && $request->header('ACCESS-KEY') === [];
    });
});

test('bitbank client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push('rate limited', 429)
        ->push([
            'success' => 1,
            'data' => [
                'trades' => [
                    ['trade_id' => 1],
                ],
            ],
        ], 200);

    $client = new BitbankClient('key', 'secret');
    $trades = $client->tradeHistory('btc_jpy');

    expect($trades[0]['trade_id'])->toBe(1);
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('bitbank client throws bitbank API errors', function () {
    Http::fake([
        'https://api.bitbank.cc/*' => Http::response([
            'success' => 0,
            'data' => ['code' => 20003],
        ], 200),
    ]);

    $client = new BitbankClient('key', 'secret');

    expect(fn () => $client->assets())
        ->toThrow(RuntimeException::class, 'bitbank API error: 20003');
});
