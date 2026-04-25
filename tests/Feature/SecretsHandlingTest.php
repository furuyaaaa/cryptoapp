<?php

/**
 * クレデンシャル取り扱いの固定化テスト。
 *
 * - `.env` が git の追跡対象から外れていること
 * - `.env.example` に平文の DB_PASSWORD 値が残っていないこと
 * - ローカル用パスワードローテーションコマンドが本番では実行できないこと
 */
test('.env が .gitignore で除外されている', function () {
    $gitignore = (string) file_get_contents(base_path('.gitignore'));
    expect($gitignore)->toContain("\n.env\n");
});

test('.env.example には DB_PASSWORD の値が書かれていない', function () {
    $envExample = (string) file_get_contents(base_path('.env.example'));

    // DB_PASSWORD= の直後は改行 (=空) であるべき。
    // 値が書かれていた場合、将来 git 履歴を通じてリークする可能性があるため NG。
    expect($envExample)->toMatch('/^DB_PASSWORD=\s*$/m');
});

test('local:rotate-db-password は本番環境で拒否される', function () {
    // APP_ENV=production を模擬（Laravel の environment() 判定を一時的に書き換え）
    $originalEnv = $this->app->environment();
    $this->app->detectEnvironment(fn () => 'production');

    try {
        $this->artisan('local:rotate-db-password')
            ->expectsOutputToContain('本番環境では実行できません')
            ->assertFailed();
    } finally {
        $this->app->detectEnvironment(fn () => $originalEnv);
    }
});

test('local:rotate-db-password はパスワード長 16 未満を拒否する', function () {
    $this->artisan('local:rotate-db-password', ['--length' => 8])
        ->expectsOutputToContain('16 文字以上')
        ->assertFailed();
});
