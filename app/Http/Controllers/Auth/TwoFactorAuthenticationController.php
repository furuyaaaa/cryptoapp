<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * プロフィール画面から 2FA を有効化／無効化／復旧コード再発行するためのコントローラ。
 *
 * フロー:
 *  - store    : シークレットを払い出し (confirm 前)
 *  - confirm  : 6桁コードで検証し、confirm 完了 → recovery codes を 1 度だけ flash
 *  - destroy  : 2FA を無効化し、関連カラムをクリア
 *  - recoveryCodes : 復旧コードを再発行 (2FA 有効時のみ)
 */
class TwoFactorAuthenticationController extends Controller
{
    public function __construct(private readonly TwoFactorAuthenticationService $service)
    {
    }

    /**
     * 2FA セットアップを開始する。シークレットだけ発行し、confirmed_at は未設定のまま。
     * 既に confirmed 済みなら何もしない。
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back();
        }

        $user->forceFill([
            'two_factor_secret' => $this->service->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    /**
     * 6桁コードを検証し、2FA を有効化する。
     * 成功時には復旧コードを 1 回だけ表示するためセッションに flash する。
     */
    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->two_factor_secret === null) {
            throw ValidationException::withMessages([
                'code' => __('2FA setup has not been started.'),
            ]);
        }

        if (! $this->service->verifyCode($user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('The provided two-factor authentication code is invalid.'),
            ]);
        }

        $recoveryCodes = $this->service->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        // 有効化した瞬間は既にこのセッションで認証済みなので、チャレンジ済み扱いに昇格する。
        $request->session()->put('auth.two_factor_verified', true);

        return back()->with('recoveryCodes', $recoveryCodes);
    }

    /**
     * 2FA を解除する。
     * パスワード確認ミドルウェア (password.confirm) 配下で呼ばれることを前提。
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('auth.two_factor_verified');

        return back();
    }

    /**
     * 復旧コードを再発行する。2FA 有効ユーザーのみ。
     */
    public function recoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            abort(403);
        }

        $recoveryCodes = $this->service->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return back()->with('recoveryCodes', $recoveryCodes);
    }
}
