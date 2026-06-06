<?php

use App\Services\Exchanges\BitFlyerClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

test('bitFlyer private API request is signed with required headers', function () {
    Http::fake([
        'https://api.bitflyer.com/v1/me/getexecutions*' => Http::response([], 200),
    ]);

    $client = new BitFlyerClient('key', 'secret');
    $client->executions('BTC_JPY', 50);

    Http::assertSent(function (Request $request) {
        $timestamp = $request->header('ACCESS-TIMESTAMP')[0] ?? '';
        $expected = hash_hmac(
            'sha256',
            $timestamp.'GET'.'/v1/me/getexecutions?product_code=BTC_JPY&count=50',
            'secret',
        );

        return $request->method() === 'GET'
            && $request->url() === 'https://api.bitflyer.com/v1/me/getexecutions?product_code=BTC_JPY&count=50'
            && ($request->header('ACCESS-KEY')[0] ?? '') === 'key'
            && ($request->header('ACCESS-SIGN')[0] ?? '') === $expected;
    });
});

test('bitFlyer client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push('rate limited', 429)
        ->push([
            ['id' => 123, 'side' => 'BUY'],
        ], 200);

    $client = new BitFlyerClient('key', 'secret');
    $executions = $client->executions();

    expect($executions)->toHaveCount(1);
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('bitFlyer market list uses public API without private headers', function () {
    Http::fake([
        'https://api.bitflyer.com/v1/markets' => Http::response([
            ['product_code' => 'BTC_JPY', 'market_type' => 'Spot'],
            ['product_code' => 'ETH_JPY', 'market_type' => 'Spot'],
        ], 200),
    ]);

    $client = new BitFlyerClient('key', 'secret');
    $markets = $client->markets();

    expect($markets)->toHaveCount(2);
    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.bitflyer.com/v1/markets'
            && $request->header('ACCESS-KEY') === [];
    });
});

test('bitFlyer market list retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push('rate limited', 429)
        ->push([
            ['product_code' => 'XRP_JPY', 'market_type' => 'Spot'],
        ], 200);

    $client = new BitFlyerClient('key', 'secret');
    $markets = $client->markets();

    expect($markets[0]['product_code'])->toBe('XRP_JPY');
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('bitFlyer client throws after retry limit', function () {
    Sleep::fake();
    Http::fake([
        'https://api.bitflyer.com/*' => Http::response('rate limited', 429),
    ]);

    $client = new BitFlyerClient('key', 'secret');

    expect(fn () => $client->executions())->toThrow(RequestException::class);
    Http::assertSentCount(4);
});
