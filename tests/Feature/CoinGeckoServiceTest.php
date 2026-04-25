<?php

use App\Services\CoinGeckoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * CoinGeckoService のリトライ／キャッシュ挙動を固定化する。
 *
 * Sleep::fake() でバックオフの待機を実時間から分離する。
 */
beforeEach(function () {
    Sleep::fake();
    Cache::flush();
});

test('成功レスポンスを 1 発で返す', function () {
    Http::fake([
        '*/simple/price*' => Http::response([
            'bitcoin' => ['jpy' => 1_000_000, 'usd' => 60_000],
        ], 200),
    ]);

    $service = app(CoinGeckoService::class);

    $prices = $service->fetchPrices(['bitcoin']);

    expect($prices)->toHaveKey('bitcoin');
    expect($prices['bitcoin']['jpy'])->toBe(1_000_000.0);
    expect($prices['bitcoin']['usd'])->toBe(60_000.0);
    Http::assertSentCount(1);
});

test('429 は最大回数までリトライし、その後成功レスポンスを返す', function () {
    Http::fakeSequence()
        ->push('rate limited', 429)
        ->push('rate limited again', 429)
        ->push(['bitcoin' => ['jpy' => 1_000_000, 'usd' => 60_000]], 200);

    $service = app(CoinGeckoService::class);

    $prices = $service->fetchPrices(['bitcoin']);

    expect($prices)->toHaveKey('bitcoin');
    Http::assertSentCount(3);
});

test('5xx も再試行対象', function () {
    Http::fakeSequence()
        ->push('boom', 503)
        ->push(['bitcoin' => ['jpy' => 1_000_000, 'usd' => 60_000]], 200);

    $service = app(CoinGeckoService::class);

    $prices = $service->fetchPrices(['bitcoin']);

    expect($prices)->toHaveKey('bitcoin');
    Http::assertSentCount(2);
});

test('404 などの非リトライ 4xx は即座に throw', function () {
    Http::fake([
        '*/simple/price*' => Http::response('not found', 404),
    ]);

    $service = app(CoinGeckoService::class);

    expect(fn () => $service->fetchPrices(['unknown-id']))
        ->toThrow(RequestException::class);

    Http::assertSentCount(1);
});

test('リトライ上限を超えると throw', function () {
    Http::fake([
        '*/simple/price*' => Http::response('rate limited', 429),
    ]);

    $service = app(CoinGeckoService::class);

    expect(fn () => $service->fetchPrices(['bitcoin']))
        ->toThrow(RequestException::class);

    // 初回 1 + リトライ 3 = 計 4 リクエスト
    Http::assertSentCount(4);
});

test('Retry-After ヘッダが秒数指定のときそれに従って待つ', function () {
    Http::fakeSequence()
        ->push('rate limited', 429, ['Retry-After' => '2'])
        ->push(['bitcoin' => ['jpy' => 1_000_000, 'usd' => 60_000]], 200);

    $service = app(CoinGeckoService::class);
    $service->fetchPrices(['bitcoin']);

    // Retry-After=2 秒 ≒ 2000 ms 前後の sleep が入っていること (バックオフ初期値 1000ms より長い)
    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds >= 2000);
});

test('同じ引数で呼び出すと 60 秒は API を叩かずキャッシュから返す', function () {
    Http::fake([
        '*/simple/price*' => Http::response([
            'bitcoin' => ['jpy' => 1_000_000, 'usd' => 60_000],
        ], 200),
    ]);

    $service = app(CoinGeckoService::class);

    $first = $service->fetchPrices(['bitcoin']);
    $second = $service->fetchPrices(['bitcoin']);

    expect($first)->toEqual($second);
    // HTTP 呼び出しは 1 回だけ
    Http::assertSentCount(1);
});

test('markets も 429 からリトライ回復する', function () {
    Http::fakeSequence()
        ->push('rate limited', 429)
        ->push([
            ['id' => 'bitcoin', 'image' => 'https://example.com/btc.png'],
        ], 200);

    $service = app(CoinGeckoService::class);

    $markets = $service->fetchMarkets(['bitcoin']);

    expect($markets)->toHaveKey('bitcoin');
    expect($markets['bitcoin']['image'])->toBe('https://example.com/btc.png');
    Http::assertSentCount(2);
});

test('接続エラーはリトライ後に最終的に throw する', function () {
    Http::fake(function () {
        throw new ConnectionException('fake timeout');
    });

    $service = app(CoinGeckoService::class);

    expect(fn () => $service->fetchPrices(['bitcoin']))
        ->toThrow(ConnectionException::class);
});
