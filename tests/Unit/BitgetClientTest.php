<?php

use App\Services\Exchanges\BitgetClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

test('Bitget signed request includes key passphrase timestamp and signature', function () {
    Http::fake([
        'https://api.bitget.com/api/v2/spot/trade/fills*' => Http::response([
            'code' => '00000',
            'msg' => 'success',
            'data' => [],
        ], 200),
    ]);

    $client = new BitgetClient('key', 'secret', 'passphrase');
    $client->fills('BTCUSDT', 1767225600000, 1767311999999, 100, '123');

    Http::assertSent(function (Request $request) {
        $timestamp = $request->header('ACCESS-TIMESTAMP')[0] ?? '';
        $query = parse_url($request->url(), PHP_URL_QUERY) ?: '';
        $payload = $timestamp.'GET'.'/api/v2/spot/trade/fills'.'?'.$query;

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.bitget.com/api/v2/spot/trade/fills')
            && ($request->header('ACCESS-KEY')[0] ?? '') === 'key'
            && ($request->header('ACCESS-PASSPHRASE')[0] ?? '') === 'passphrase'
            && ($request->header('ACCESS-SIGN')[0] ?? '') === base64_encode(hash_hmac('sha256', $payload, 'secret', true))
            && str_contains($query, 'symbol=BTCUSDT')
            && str_contains($query, 'startTime=1767225600000')
            && str_contains($query, 'endTime=1767311999999')
            && str_contains($query, 'idLessThan=123');
    });
});

test('Bitget public symbols use public API without private headers', function () {
    Http::fake([
        'https://api.bitget.com/api/v2/spot/public/symbols' => Http::response([
            'code' => '00000',
            'msg' => 'success',
            'data' => [
                ['symbol' => 'BTCUSDT', 'quoteCoin' => 'USDT'],
            ],
        ], 200),
    ]);

    $client = new BitgetClient('key', 'secret', 'passphrase');
    $symbols = $client->symbols();

    expect($symbols[0]['symbol'])->toBe('BTCUSDT');
    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.bitget.com/api/v2/spot/public/symbols'
            && $request->header('ACCESS-KEY') === [];
    });
});

test('Bitget client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push(['code' => '429', 'msg' => 'Too many requests'], 429)
        ->push(['code' => '00000', 'msg' => 'success', 'data' => []], 200);

    $client = new BitgetClient('key', 'secret', 'passphrase');
    $client->assets();

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('Bitget client throws API errors', function () {
    Http::fake([
        'https://api.bitget.com/api/v2/spot/account/assets*' => Http::response([
            'code' => '40009',
            'msg' => 'sign signature error',
        ], 200),
    ]);

    $client = new BitgetClient('key', 'secret', 'passphrase');

    expect(fn () => $client->assets())
        ->toThrow(RuntimeException::class, 'Bitget API error: sign signature error');
});
