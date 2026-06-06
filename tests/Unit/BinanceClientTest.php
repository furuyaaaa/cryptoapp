<?php

use App\Services\Exchanges\BinanceClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

test('Binance signed request includes API key timestamp and signature', function () {
    Http::fake([
        'https://api.binance.com/api/v3/myTrades*' => Http::response([], 200),
    ]);

    $client = new BinanceClient('key', 'secret');
    $client->myTrades('BTCJPY', 1767225600000, 1767311999999, 100);

    Http::assertSent(function (Request $request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);
        $signature = $query['signature'] ?? '';
        unset($query['signature']);
        $payload = http_build_query($query, '', '&');

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.binance.com/api/v3/myTrades')
            && ($request->header('X-MBX-APIKEY')[0] ?? '') === 'key'
            && ($query['symbol'] ?? null) === 'BTCJPY'
            && ($query['startTime'] ?? null) === '1767225600000'
            && ($query['endTime'] ?? null) === '1767311999999'
            && hash_hmac('sha256', $payload, 'secret') === $signature;
    });
});

test('Binance public exchange info uses public API without private headers', function () {
    Http::fake([
        'https://api.binance.com/api/v3/exchangeInfo' => Http::response([
            'symbols' => [
                ['symbol' => 'BTCJPY', 'quoteAsset' => 'JPY'],
            ],
        ], 200),
    ]);

    $client = new BinanceClient('key', 'secret');
    $exchangeInfo = $client->exchangeInfo();

    expect($exchangeInfo['symbols'][0]['symbol'])->toBe('BTCJPY');
    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.binance.com/api/v3/exchangeInfo'
            && $request->header('X-MBX-APIKEY') === [];
    });
});

test('Binance client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push(['code' => -1003, 'msg' => 'Too many requests'], 429)
        ->push([], 200);

    $client = new BinanceClient('key', 'secret');
    $client->account();

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('Binance client throws API errors', function () {
    Http::fake([
        'https://api.binance.com/api/v3/account*' => Http::response([
            'code' => -2015,
            'msg' => 'Invalid API-key, IP, or permissions for action.',
        ], 401),
    ]);

    $client = new BinanceClient('key', 'secret');

    expect(fn () => $client->account())
        ->toThrow(RuntimeException::class, 'Binance API error: Invalid API-key, IP, or permissions for action.');
});
