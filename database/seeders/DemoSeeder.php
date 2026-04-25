<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 開発/デモ環境専用のシーダー。
 *
 * - 固定の test@example.com ユーザーを作成する
 * - サンプルポートフォリオ/取引を作成する
 *
 * 本番環境 (APP_ENV=production) では絶対に実行されない。
 * ステージングなど APP_ENV が production 以外の環境で「意図的に」
 * 流したい場合は `php artisan db:seed --class=DemoSeeder` を使うこと。
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn(
                'DemoSeeder は本番環境 (APP_ENV=production) では実行されません。スキップしました。'
            );

            return;
        }

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->call([
            PortfolioSeeder::class,
        ]);
    }
}
