<?php

use App\Models\User;
use Illuminate\Foundation\Application;
use Laravel\Sanctum\Sanctum;
use Stripe\Subscription as StripeSubscription;

beforeEach(function () {
    config(['billing.required' => true]);
});

/**
 * @return Closure(): void restore を叩くと元に戻す
 */
function bindAppEnvForTest(Application $app, string $env): Closure
{
    $previous = $app['env'];
    $app['env'] = $env;

    return static function () use ($app, $previous): void {
        $app['env'] = $previous;
    };
}

test('未契約の Web ユーザーはダッシュボードから課金ページへリダイレクトされる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('billing'));
});

test('APP_ENV=local 相当では未契約の Web ユーザーもダッシュボードを表示できる', function () {
    $user = User::factory()->create();
    $restore = bindAppEnvForTest($this->app, 'local');

    try {
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    } finally {
        $restore();
    }
});

test('未契約でも課金ページは表示できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('billing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Billing/Index'));
});

test('契約済み Web ユーザーはダッシュボードを表示できる', function () {
    $user = User::factory()->create();
    $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => StripeSubscription::STATUS_ACTIVE,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('未契約の API ユーザーはダッシュボード取得で 402 を返す', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/dashboard')
        ->assertStatus(402)
        ->assertJsonFragment(['message' => '有料プランへの加入が必要です。']);
});

test('APP_ENV=local 相当では未契約の API ユーザーもダッシュボードを取得できる', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $restore = bindAppEnvForTest($this->app, 'local');

    try {
        $this->getJson('/api/v1/dashboard')->assertOk();
    } finally {
        $restore();
    }
});

test('未契約の管理者はダッシュボードから管理者用課金ページへリダイレクトされる', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.billing'));
});

test('管理者が一般向け課金 URL にアクセスすると管理者向けへリダイレクトされる', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('billing'))
        ->assertRedirect(route('admin.billing'));
});

test('管理者は管理者用課金ページを表示できる', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.billing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Billing/Index'));
});

test('非管理者は管理者用課金 URL にアクセスできない', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.billing'))
        ->assertForbidden();
});

test('契約済み管理者はダッシュボードを表示できる', function () {
    $admin = User::factory()->admin()->create();
    $admin->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.uniqid(),
        'stripe_status' => StripeSubscription::STATUS_ACTIVE,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();
});

test('未契約の API 管理者はダッシュボードを取得できる', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/dashboard')->assertOk();
});
