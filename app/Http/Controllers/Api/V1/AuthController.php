<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(private readonly TwoFactorAuthenticationService $twoFactor) {}

    /**
     * モバイル用: メール・パスワードでログインし Sanctum トークンを返す。
     * 2FA 有効時は `one_time_password`（TOTP または復旧コード）が必須。
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'one_time_password' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if ($user->hasPendingTwoFactor()) {
            throw ValidationException::withMessages([
                'email' => ['二要素認証の設定が未完了です。Web のプロフィールから完了してください。'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $otp = trim((string) ($validated['one_time_password'] ?? ''));
            if ($otp === '') {
                return response()->json([
                    'message' => '二要素認証コードが必要です。',
                    'two_factor_required' => true,
                ], 422);
            }

            $verified = $this->twoFactor->verifyCode((string) $user->two_factor_secret, $otp);

            if (! $verified) {
                $remaining = $this->twoFactor->consumeRecoveryCode(
                    $user->two_factor_recovery_codes ?? [],
                    $otp,
                );

                if ($remaining === null) {
                    throw ValidationException::withMessages([
                        'one_time_password' => ['認証コードまたは復旧コードが正しくありません。'],
                    ]);
                }

                $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
            }
        }

        $device = $validated['device_name'] ?? 'mobile';
        $token = $user->createToken($device)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $plain = $request->bearerToken()
            ?? $this->bearerFromAuthorizationHeader($request->header('Authorization'));

        if ($plain) {
            PersonalAccessToken::findToken($plain)?->delete();
        } else {
            $request->user()?->currentAccessToken()?->delete();
        }

        // テストやハイブリッド利用で Web セッションにログイン状態が残ると、トークン失効後も認証済みになるため明示的に切る。
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'ログアウトしました。']);
    }

    private function bearerFromAuthorizationHeader(?string $authorization): ?string
    {
        if ($authorization === null || $authorization === '') {
            return null;
        }

        if (preg_match('/Bearer\s+(\S+)/i', $authorization, $m)) {
            return $m[1];
        }

        return null;
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'is_admin' => $user->isAdmin(),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
        ];
    }

}
