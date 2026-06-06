<?php

use App\Services\Exchanges\ZaifClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

test('Zaif private API request is signed with required headers', function () {
    Http::fake([
        'https://api.zaif.jp/tapi' => Http::response([
            'success' => 1,
            'return' => [],
        ], 200),
    ]);

    $client = new ZaifClient('key', 'secret');
    $client->tradeHistory('btc_jpy', 1767225600, 50);

    Http::assertSent(function (Request $request) {
        $signature = hash_hmac('sha512', $request->body(), 'secret');

        return $request->method() === 'POST'
            && $request->url() === 'https://api.zaif.jp/tapi'
            && ($request->header('key')[0] ?? '') === 'key'
            && ($request->header('sign')[0] ?? '') === $signature
            && str_contains($request->body(), 'method=trade_history')
            && str_contains($request->body(), 'currency_pair=btc_jpy')
            && str_contains($request->body(), 'since=1767225600');
    });
});

test('Zaif public currency pairs use public API without private headers', function () {
    Http::fake([
        'https://api.zaif.jp/api/1/currency_pairs/all' => Http::response([
            ['currency_pair' => 'btc_jpy', 'is_token' => false],
        ], 200),
    ]);

    $client = new ZaifClient('key', 'secret');
    $pairs = $client->currencyPairs();

    expect($pairs[0]['currency_pair'])->toBe('btc_jpy');
    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.zaif.jp/api/1/currency_pairs/all'
            && $request->header('key') === [];
    });
});

test('Zaif client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push('rate limited', 429)
        ->push([
            'success' => 1,
            'return' => [
                '182' => ['currency_pair' => 'btc_jpy'],
            ],
        ], 200);

    $client = new ZaifClient('key', 'secret');
    $trades = $client->tradeHistory('btc_jpy');

    expect($trades['182']['currency_pair'])->toBe('btc_jpy');
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('Zaif client throws API errors', function () {
    Http::fake([
        'https://api.zaif.jp/tapi' => Http::response([
            'success' => 0,
            'return' => 'signature mismatch',
        ], 200),
    ]);

    $client = new ZaifClient('key', 'secret');

    expect(fn () => $client->info())
        ->toThrow(RuntimeException::class, 'Zaif API error: signature mismatch');
});
