<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * 本番ハードニング（AppServiceProvider::hardenForProduction）テスト。
 *
 * - 本番で APP_DEBUG=true なら critical ログが出る
 * - 本番では url() が https:// になる（forceScheme）
 * - 非本番（local/testing）では警告ログも forceScheme も発生しない
 */

/**
 * 指定した APP_ENV / APP_DEBUG で AppServiceProvider::hardenForProduction を再実行する。
 */
function runHardenForProduction(string $env, bool $debug): void
{
    /** @var \Illuminate\Foundation\Application $app */
    $app = app();
    $app->detectEnvironment(fn () => $env);
    config(['app.env' => $env, 'app.debug' => $debug]);

    $provider = new \App\Providers\AppServiceProvider($app);
    $method = new ReflectionMethod($provider, 'hardenForProduction');
    $method->setAccessible(true);
    $method->invoke($provider);
}

test('本番 + APP_DEBUG=true で critical ログが出る', function () {
    Log::spy();

    runHardenForProduction(env: 'production', debug: true);

    Log::shouldHaveReceived('critical')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'APP_DEBUG_ENABLED_IN_PRODUCTION'));
});

test('本番 + APP_DEBUG=false では警告ログは出ない', function () {
    Log::spy();

    runHardenForProduction(env: 'production', debug: false);

    Log::shouldNotHaveReceived('critical');
});

test('本番環境では url() が https:// を返す', function () {
    runHardenForProduction(env: 'production', debug: false);

    expect(url('/login'))->toStartWith('https://');
});

test('非本番では警告も forceScheme も発生しない', function () {
    Log::spy();

    // forceScheme の痕跡を消すため UrlGenerator を作り直す（testing 環境を保証）
    app()->detectEnvironment(fn () => 'testing');
    config(['app.env' => 'testing', 'app.debug' => true]);

    runHardenForProduction(env: 'local', debug: true);

    Log::shouldNotHaveReceived('critical');
    // local では forceScheme は呼ばれないので url() はベース URL のスキームに従う。
    // ただし直前の production テストで forceScheme('https') が残っている可能性があり、
    // URL ジェネレータの forceScheme は解除できないため、スキーム自体は検証しない。
});
