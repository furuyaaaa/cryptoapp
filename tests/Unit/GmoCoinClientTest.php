<?php

use App\Services\Exchanges\GmoCoinClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

test('GMO Coin private API request is signed with required headers', function () {
    Http::fake([
        'https://api.coin.z.com/private/v1/latestExecutions*' => Http::response([
            'status' => 0,
            'data' => ['list' => []],
        ], 200),
    ]);

    $client = new GmoCoinClient('key', 'secret');
    $client->latestExecutions('BTC', 1, 50);

    Http::assertSent(function (Request $request) {
        $timestamp = $request->header('API-TIMESTAMP')[0] ?? '';
        $signature = hash_hmac('sha256', $timestamp.'GET'.'/v1/latestExecutions', 'secret');

        return $request->method() === 'GET'
            && $request->url() === 'https://api.coin.z.com/private/v1/latestExecutions?symbol=BTC&page=1&count=50'
            && ($request->header('API-KEY')[0] ?? '') === 'key'
            && ($request->header('API-SIGN')[0] ?? '') === $signature;
    });
});

test('GMO Coin client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push('rate limited', 429)
        ->push([
            'status' => 0,
            'data' => [
                'list' => [
                    ['executionId' => 123, 'symbol' => 'BTC'],
                ],
            ],
        ], 200);

    $client = new GmoCoinClient('key', 'secret');
    $executions = $client->latestExecutions('BTC');

    expect($executions[0]['executionId'])->toBe(123);
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('GMO Coin client throws API errors', function () {
    Http::fake([
        'https://api.coin.z.com/private/*' => Http::response([
            'status' => 1,
            'messages' => [
                ['message_code' => 'ERR-5003', 'message_string' => 'authentication failed'],
            ],
        ], 200),
    ]);

    $client = new GmoCoinClient('key', 'secret');

    expect(fn () => $client->assets())
        ->toThrow(RuntimeException::class, 'GMO Coin API error: authentication failed');
});
