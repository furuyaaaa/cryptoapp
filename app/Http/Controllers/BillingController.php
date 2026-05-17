<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BillingController extends Controller
{
    public function index(Request $request): InertiaResponse|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.billing');
        }

        $user = $request->user();
        $priceId = config('billing.price_id');

        return Inertia::render('Billing/Index', [
            'subscribed' => $user->subscribed('default'),
            'priceConfigured' => filled($priceId),
            'hasStripeCustomer' => $user->hasStripeId(),
            'checkout' => $request->query('checkout'),
            'sessionId' => $request->query('session_id'),
            'subscriptionRequired' => (bool) config('billing.required', true),
        ]);
    }

    /**
     * 管理者向け: Stripe 設定・運用メモを含む課金画面（未契約時の着地先）。
     */
    public function adminIndex(Request $request): InertiaResponse
    {
        $user = $request->user();
        $priceId = config('billing.price_id');

        return Inertia::render('Admin/Billing/Index', [
            'subscribed' => $user->subscribed('default'),
            'priceConfigured' => filled($priceId),
            'hasStripeCustomer' => $user->hasStripeId(),
            'checkout' => $request->query('checkout'),
            'sessionId' => $request->query('session_id'),
            'subscriptionRequired' => (bool) config('billing.required', true),
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $priceId = config('billing.price_id');
        $billingReturnRoute = $request->user()->isAdmin()
            ? 'admin.billing'
            : 'billing';

        if (! filled($priceId)) {
            return redirect()
                ->route($billingReturnRoute)
                ->with('error', 'Stripe の Price ID（STRIPE_PRICE_ID）が未設定です。.env を確認してください。');
        }

        $returnUrl = route($billingReturnRoute, absolute: true);

        return $request->user()
            ->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => $returnUrl.'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $returnUrl.'?checkout=cancel',
                'payment_method_types' => ['card'],
            ]);
    }

    public function portal(Request $request): RedirectResponse
    {
        $billingReturnRoute = $request->user()->isAdmin()
            ? 'admin.billing'
            : 'billing';

        if (! $request->user()->hasStripeId()) {
            return redirect()
                ->route($billingReturnRoute)
                ->with('error', '先にプランへのお申し込み（Checkout）を完了してください。');
        }

        return $request->user()->redirectToBillingPortal(route($billingReturnRoute));
    }
}
