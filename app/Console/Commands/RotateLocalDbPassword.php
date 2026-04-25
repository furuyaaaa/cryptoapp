<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * ローカル開発環境向けの DB パスワードをランダム生成して `.env` を書き換えるヘルパ。
 *
 * 目的:
 *  - `.env` に覚えやすい平文（"password" や "postgres"）を固定で書き続けるのを防ぐ
 *  - 各開発者マシンで独立したランダムパスワードを持つ状態を簡単に維持する
 *
 * 安全装置:
 *  - APP_ENV=production では絶対に実行させない（本番シークレットは外部マネージャで管理するため）
 *  - 実行後は開発者自身が `ALTER USER ... WITH PASSWORD '...'` を PostgreSQL 側で適用する必要がある。
 *    そのための SQL も併せて出力する。
 */
class RotateLocalDbPassword extends Command
{
    protected $signature = 'local:rotate-db-password {--length=32 : 生成するパスワードの長さ（16 未満は拒否）}';

    protected $description = 'ローカル開発用 DB パスワードをランダム生成し .env を書き換える（本番では実行不可）';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('本番環境では実行できません。本番のシークレットは外部シークレットマネージャで管理してください。');

            return self::FAILURE;
        }

        $length = (int) $this->option('length');
        if ($length < 16) {
            $this->error('パスワード長は 16 文字以上にしてください。');

            return self::FAILURE;
        }

        $password = bin2hex(random_bytes((int) ($length / 2)));

        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            $this->error('.env が見つかりません。先に .env.example をコピーしてください。');

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($envPath);
        $username = $this->extractUsernameFromEnv($contents);

        if (preg_match('/^DB_PASSWORD=.*$/m', $contents)) {
            $contents = preg_replace('/^DB_PASSWORD=.*$/m', 'DB_PASSWORD='.$password, $contents);
        } else {
            $contents .= PHP_EOL.'DB_PASSWORD='.$password.PHP_EOL;
        }

        file_put_contents($envPath, $contents);

        $this->info('新しい DB_PASSWORD を .env に書き込みました。');
        $this->line('');
        $this->warn('PostgreSQL 側でも同じパスワードに更新してください:');
        $this->line("  ALTER USER {$username} WITH PASSWORD '{$password}';");
        $this->line('');
        $this->warn('phpunit.xml のテスト用 DB_PASSWORD も同期が必要な場合は手動で反映してください。');

        return self::SUCCESS;
    }

    private function extractUsernameFromEnv(string $contents): string
    {
        if (preg_match('/^DB_USERNAME=(.*)$/m', $contents, $m)) {
            return trim($m[1]) ?: 'cryptoapp_app';
        }

        return 'cryptoapp_app';
    }
}
