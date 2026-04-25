<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理者アカウントは 2FA を必須とする。
 *
 * 管理画面のルートでのみ適用し、2FA 未設定なら `/profile` に誘導する。
 * これにより管理者ユーザーは 2FA をセットアップしない限り管理操作ができない。
 *
 * 前提: このミドルウェアは `admin` ミドルウェアの後ろで使うこと
 *      （ログイン済み＋is_admin=true はすでに保証されている）。
 */
class EnsureAdminHasTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isAdmin() && ! $user->hasTwoFactorEnabled()) {
            if ($request->expectsJson()) {
                abort(403, 'Two-factor authentication is required for administrators.');
            }

            return redirect()
                ->route('profile.edit')
                ->with('error', '管理者アカウントは 2FA の設定が必須です。下記フォームから有効化してください。');
        }

        return $next($request);
    }
}
