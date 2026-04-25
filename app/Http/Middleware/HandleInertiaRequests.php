<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'is_admin' => (bool) $user?->is_admin,
                'two_factor_enabled' => (bool) $user?->hasTwoFactorEnabled(),
                'two_factor_pending' => (bool) $user?->hasPendingTwoFactor(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // 2FA 有効化直後に 1 度だけ表示するための復旧コード
                'recoveryCodes' => fn () => $request->session()->get('recoveryCodes'),
            ],
        ];
    }
}
