<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
            '2fa' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'admin.2fa' => \App\Http\Middleware\EnsureAdminHasTwoFactor::class,
        ]);

        // リバースプロキシ配下で Secure Cookie と正しいホスト名を有効にするため、
        // TRUSTED_PROXIES を設定した場合のみ X-Forwarded-* を信頼する。
        // 値は個別IP/CIDRのカンマ区切り、または全信頼の '*' を指定。
        $trustedProxies = env('TRUSTED_PROXIES');
        if ($trustedProxies !== null && $trustedProxies !== '') {
            $middleware->trustProxies(
                at: $trustedProxies === '*'
                    ? '*'
                    : array_values(array_filter(array_map('trim', explode(',', (string) $trustedProxies)))),
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
                    | Request::HEADER_X_FORWARDED_AWS_ELB,
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
