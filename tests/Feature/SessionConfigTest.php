<?php

use Illuminate\Support\Env;

/**
 * config/session.php の「安全側デフォルト」を固定するテスト。
 *
 * - 本番 (APP_ENV=production) で SESSION_SECURE_COOKIE を明示しなくても Secure=true
 * - 非本番では Secure=false（ローカル HTTP 開発を壊さない）
 * - SESSION_SECURE_COOKIE を明示すればどちらの環境でも上書きできる
 * - http_only / same_site / encrypt のデフォルトが運用上安全な値
 *
 * Laravel の env リポジトリは immutable かつシングルトンなので、
 * テスト側で $_ENV / $_SERVER / getenv() を書き換えた上で
 * Env::enablePutenv() を呼び直してリポジトリを再生成する必要がある。
 */

/**
 * 指定した env 値で config/session.php を読み込み、復元する。
 *
 * @param  array<string, string|null>  $overrides  null を渡すとキーを消す
 */
function loadSessionConfigWith(array $overrides): array
{
    $trackedKeys = array_unique(array_merge(
        array_keys($overrides),
        ['APP_ENV', 'SESSION_SECURE_COOKIE', 'SESSION_HTTP_ONLY', 'SESSION_SAME_SITE', 'SESSION_ENCRYPT']
    ));

    $backup = [];
    foreach ($trackedKeys as $key) {
        $backup[$key] = [
            'env' => array_key_exists($key, $_ENV) ? $_ENV[$key] : '__unset__',
            'server' => array_key_exists($key, $_SERVER) ? $_SERVER[$key] : '__unset__',
            'getenv' => getenv($key),
        ];
    }

    try {
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        // immutable リポジトリをリセット
        Env::enablePutenv();

        return require base_path('config/session.php');
    } finally {
        foreach ($backup as $key => $state) {
            if ($state['env'] === '__unset__') {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $state['env'];
            }
            if ($state['server'] === '__unset__') {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $state['server'];
            }
            if ($state['getenv'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$state['getenv']}");
            }
        }
        Env::enablePutenv();
    }
}

test('本番環境では secure cookie がデフォルトで true になる', function () {
    $config = loadSessionConfigWith([
        'APP_ENV' => 'production',
        'SESSION_SECURE_COOKIE' => null,
    ]);

    expect($config['secure'])->toBeTrue();
});

test('非本番環境では secure cookie がデフォルトで false のまま', function () {
    $config = loadSessionConfigWith([
        'APP_ENV' => 'local',
        'SESSION_SECURE_COOKIE' => null,
    ]);

    expect($config['secure'])->toBeFalse();
});

test('SESSION_SECURE_COOKIE を明示すれば本番デフォルトを上書きできる', function () {
    $config = loadSessionConfigWith([
        'APP_ENV' => 'production',
        'SESSION_SECURE_COOKIE' => 'false',
    ]);

    expect($config['secure'])->toBeFalse();
});

test('session cookie は http_only / same_site=lax / encrypt=true がデフォルト', function () {
    $config = loadSessionConfigWith([
        'APP_ENV' => 'production',
        'SESSION_HTTP_ONLY' => null,
        'SESSION_SAME_SITE' => null,
        'SESSION_ENCRYPT' => null,
    ]);

    expect($config['http_only'])->toBeTrue();
    expect($config['same_site'])->toBe('lax');
    expect($config['encrypt'])->toBeTrue();
});

test('SESSION_SAME_SITE=strict を環境変数で強化できる', function () {
    $config = loadSessionConfigWith([
        'SESSION_SAME_SITE' => 'strict',
    ]);

    expect($config['same_site'])->toBe('strict');
});
