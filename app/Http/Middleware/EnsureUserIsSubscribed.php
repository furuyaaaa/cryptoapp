<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSubscribed
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('billing.required', true)) {
            return $next($request);
        }

        if (app()->environment('local')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->subscribed('default')) {
            return $next($request);
        }

        if ($request->is('api/*') && $user->isAdmin()) {
            return $next($request);
        }

        $billingRouteNames = [
            'billing',
            'billing.checkout',
            'billing.portal',
        ];

        if ($user->isAdmin()) {
            $billingRouteNames[] = 'admin.billing';
        }

        if ($request->routeIs(...$billingRouteNames)) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return response()->json([
                'message' => '有料プランへの加入が必要です。',
            ], 402);
        }

        return redirect()->route($user->isAdmin() ? 'admin.billing' : 'billing');
    }
}
