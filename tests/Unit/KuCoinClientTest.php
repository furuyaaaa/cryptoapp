<?php

use App\Services\Exchanges\KuCoinClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

test('KuCoin signed request includes key passphrase timestamp and signature', function () {
    Http::fake([
        'https://api.kucoin.com/api/v1/hf/fills*' => Http::response([
            'code' => '200000',
            'data' => ['items' => [], 'lastId' => null],
        ], 200),
    ]);

    $client = new KuCoinClient('key', 'secret', 'passphrase');
    $client->fills('BTC-USDT', 1767225600000, 1767311999999, 100, '123');

    Http::assertSent(function (Request $request) {
        $timestamp = $request->header('KC-API-TIMESTAMP')[0] ?? '';
        $query = parse_url($request->url(), PHP_URL_QUERY) ?: '';
        $payload = $timestamp.'GET'.'/api/v1/hf/fills'.'?'.$query;

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.kucoin.com/api/v1/hf/fills')
            && ($request->header('KC-API-KEY')[0] ?? '') === 'key'
            && ($request->header('KC-API-KEY-VERSION')[0] ?? '') === '2'
            && ($request->header('KC-API-SIGN')[0] ?? '') === base64_encode(hash_hmac('sha256', $payload, 'secret', true))
            && ($request->header('KC-API-PASSPHRASE')[0] ?? '') === base64_encode(hash_hmac('sha256', 'passphrase', 'secret', true))
            && str_contains($query, 'symbol=BTC-USDT')
            && str_contains($query, 'startAt=1767225600000')
            && str_contains($query, 'endAt=1767311999999')
            && str_contains($query, 'lastId=123');
    });
});

test('KuCoin public symbols use public API without private headers', function () {
    Http::fake([
        'https://api.kucoin.com/api/v2/symbols' => Http::response([
            'code' => '200000',
            'data' => [
                ['symbol' => 'BTC-USDT', 'quoteCurrency' => 'USDT'],
            ],
        ], 200),
    ]);

    $client = new KuCoinClient('key', 'secret', 'passphrase');
    $symbols = $client->symbols();

    expect($symbols[0]['symbol'])->toBe('BTC-USDT');
    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.kucoin.com/api/v2/symbols'
            && $request->header('KC-API-KEY') === [];
    });
});

test('KuCoin client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push(['code' => '429000', 'msg' => 'Too Many Requests'], 429, ['gw-ratelimit-reset' => '1000'])
        ->push(['code' => '200000', 'data' => []], 200);

    $client = new KuCoinClient('key', 'secret', 'passphrase');
    $client->apiKeyInfo();

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('KuCoin client throws API errors', function () {
    Http::fake([
        'https://api.kucoin.com/api/v1/user/api-key*' => Http::response([
            'code' => '400005',
            'msg' => 'Invalid KC-API-SIGN',
        ], 200),
    ]);

    $client = new KuCoinClient('key', 'secret', 'passphrase');

    expect(fn () => $client->apiKeyInfo())
        ->toThrow(RuntimeException::class, 'KuCoin API error: Invalid KC-API-SIGN');
});
