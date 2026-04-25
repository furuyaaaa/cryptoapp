<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * 本番環境でも安全に実行できるマスタデータのみを流すエントリポイント。
 *
 * 本番環境 (APP_ENV=production) で `php artisan db:seed` を誤実行しても、
 * 既知のパスワード/メールを持つユーザーが作られないようにする。
 *
 * デモ用ユーザー・サンプル取引は {@see DemoSeeder} に分離しており、
 * 非本番環境でのみ自動的にチェイン実行される。
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AssetSeeder::class,
            ExchangeSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call([
                DemoSeeder::class,
            ]);
        } else {
            $this->command?->info(
                '本番環境のため DemoSeeder はスキップしました。'
                .'デモデータが必要な場合は明示的に `--class=DemoSeeder` を指定してください（非推奨）。'
            );
        }
    }
}
