<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ログイン済み & 2FA 有効ユーザーが TOTP コード / 復旧コードを入力する画面。
 * ミドルウェア `auth` 必須。通過するとセッションに `auth.two_factor_verified = true` を書く。
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorAuthenticationService $service)
    {
    }

    public function create(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        if ($request->session()->get('auth.two_factor_verified') === true) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (empty($validated['code']) && empty($validated['recovery_code'])) {
            throw ValidationException::withMessages([
                'code' => __('Please enter the authentication code or a recovery code.'),
            ]);
        }

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        if (! empty($validated['recovery_code'])) {
            $remaining = $this->service->consumeRecoveryCode(
                $user->two_factor_recovery_codes ?? [],
                $validated['recovery_code'],
            );

            if ($remaining === null) {
                throw ValidationException::withMessages([
                    'recovery_code' => __('The recovery code is invalid.'),
                ]);
            }

            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
        } else {
            if (! $this->service->verifyCode($user->two_factor_secret, $validated['code'])) {
                throw ValidationException::withMessages([
                    'code' => __('The provided two-factor authentication code is invalid.'),
                ]);
            }
        }

        $request->session()->put('auth.two_factor_verified', true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
