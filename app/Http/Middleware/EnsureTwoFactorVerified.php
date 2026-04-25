<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA 有効ユーザーがログインした直後、TOTP チャレンジを通過するまで
 * 業務画面へのアクセスを遮断するミドルウェア。
 *
 * セッションフラグ `auth.two_factor_verified` をチャレンジ成功時に true にする。
 * 2FA が未設定 / 未確認 のユーザーは素通しする。
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->session()->get('auth.two_factor_verified') === true) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Two-factor authentication required.');
        }

        // チャレンジ通過後に元いた画面へ戻すため、GET なら現在URLを intended に保持する。
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('two-factor.challenge');
    }
}
