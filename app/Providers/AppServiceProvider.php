<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->hardenForProduction();
        $this->configureRateLimiters();
    }

    /**
     * アプリケーションで使うレートリミッターの名前付き定義。
     *
     * - writes:     書き込み系（POST/PUT/PATCH/DELETE）を含むログイン後のアクション上限
     * - exports:    CSV エクスポートなど重い処理。DBスキャン負荷とディスク書き出しを抑える
     * - auth-post:  登録・パスワードリセットなど未認証 POST のブルートフォース対策
     * - two-factor: 2FA 検証系。ユーザー単位 5/min + IP 単位 20/min の二重バケットで
     *               TOTP / 復旧コードのブルートフォースを抑制する
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('writes', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(60)->by('writes:'.$key);
        });

        RateLimiter::for('exports', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute(10)->by('exports:'.$key);
        });

        RateLimiter::for('auth-post', function (Request $request) {
            return Limit::perMinute(5)->by('auth-post:'.$request->ip());
        });

        // 2FA 専用: 配列で返すと各 Limit が独立して評価される。
        // - 個人ユーザーが同一アカウントで 5/min までしか試せない（未ログイン時は IP にフォールバック）
        // - 同一 IP からの攻撃者が 20/min を超えて複数ユーザーに対して推測試行することを防ぐ
        RateLimiter::for('two-factor', function (Request $request) {
            $userKey = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinute(5)->by('two-factor:user:'.$userKey),
                Limit::perMinute(20)->by('two-factor:ip:'.$request->ip()),
            ];
        });
    }

    /**
     * 本番環境 (APP_ENV=production) 向けの安全装置をまとめて適用する。
     *
     * - APP_DEBUG=true のまま本番起動された場合は critical ログを毎回吐く
     *   （スタックトレースやクレデンシャル漏洩のリスクが極めて高いため気付きやすくする）
     * - リバースプロキシ背後でもリンク生成が https:// になるよう URL スキームを固定
     */
    protected function hardenForProduction(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        if (config('app.debug')) {
            // 運用オペレータが気付けるように Log チャネルに明確な文字列で残す。
            // CI/監視ダッシュボードで "APP_DEBUG_ENABLED_IN_PRODUCTION" を検知対象にする想定。
            Log::critical(
                'APP_DEBUG_ENABLED_IN_PRODUCTION: APP_DEBUG=true in production environment. '
                .'This leaks stack traces and environment values. Set APP_DEBUG=false immediately.'
            );
        }

        // TrustProxies が有効なら X-Forwarded-Proto から自動で https と判定されるが、
        // プロキシ設定ミス/未設定でも本番ではリンク生成を https に固定しておく。
        URL::forceScheme('https');
    }
}
