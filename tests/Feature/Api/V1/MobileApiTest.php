<?php

use App\Models\Asset;
use App\Models\Exchange;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

function apiTotp(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

test('API: 未認証はダッシュボードを取得できない', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

test('API: メールとパスワードでログインしトークンが返る', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'pest',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email', 'name']])
        ->assertJson(['token_type' => 'Bearer']);

    expect($response->json('user.id'))->toBe($user->id);
});

test('API: 認証情報が誤れば 422', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();
});

test('API: 2FA 有効ユーザーはコードなしだと two_factor_required', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertStatus(422)
        ->assertJson(['two_factor_required' => true]);
});

test('API: 2FA 有効ユーザーは正しい TOTP でトークン取得できる', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'one_time_password' => apiTotp($secret),
    ])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('API: 2FA 有効ユーザーは復旧コードでもログインできコードが消費される', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $codes = $service->generateRecoveryCodes();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $codes,
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'one_time_password' => $codes[0],
    ])->assertOk();

    $user->refresh();
    expect($user->two_factor_recovery_codes)->toHaveCount(7);
});

test('API: Sanctum トークンでダッシュボード JSON が取得できる', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'totals' => ['valuation', 'cost_basis', 'profit'],
            'allocation',
            'topHoldings',
            'recentTransactions',
        ]);
});

test('API: user エンドポイントが本人情報を返す', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

test('API: logout でトークンが失効する', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    expect($user->tokens()->count())->toBe(1);

    $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$token,
    ])->assertOk();

    // 同一テスト内で Kernel が解決した auth ガードのユーザーが残るため、以降の未認証検証前に掃除する。
    Auth::forgetGuards();

    expect($user->refresh()->tokens()->count())->toBe(0);

    $this->getJson('/api/v1/dashboard')->assertUnauthorized();

    $this->getJson('/api/v1/dashboard', [
        'Authorization' => 'Bearer '.$token,
    ])->assertUnauthorized();
});

test('API: ポートフォリオ一覧は本人分のみ', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    Portfolio::factory()->for($me)->create(['name' => 'Mine']);
    Portfolio::factory()->for($other)->create(['name' => 'Other']);

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/portfolios')
        ->assertOk()
        ->assertJsonCount(1, 'portfolios');
});

test('API: 他人のポートフォリオは更新できない', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $p = Portfolio::factory()->for($other)->create();

    Sanctum::actingAs($me);

    $this->patchJson("/api/v1/portfolios/{$p->id}", [
        'name' => 'Hacked',
        'description' => null,
    ])->assertForbidden();
});

test('API: ポートフォリオを作成・更新・削除できる', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/portfolios', [
        'name' => 'API PF',
        'description' => 'desc',
    ])
        ->assertCreated()
        ->assertJsonPath('portfolio.name', 'API PF');

    $id = $this->postJson('/api/v1/portfolios', [
        'name' => 'Second',
        'description' => null,
    ])->assertCreated()->json('portfolio.id');

    $this->patchJson("/api/v1/portfolios/{$id}", [
        'name' => 'Renamed',
        'description' => null,
    ])
        ->assertOk()
        ->assertJsonPath('portfolio.name', 'Renamed');

    $this->deleteJson("/api/v1/portfolios/{$id}")
        ->assertNoContent();
});

test('API: 取引フォーム用オプションが取得できる', function () {
    $user = User::factory()->create();
    Portfolio::factory()->for($user)->create();
    Asset::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/transactions/form')
        ->assertOk()
        ->assertJsonStructure(['portfolios', 'assets', 'exchanges', 'types', 'defaultPortfolioId']);
});

test('API: 取引を作成し一覧に反映される', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create();
    $exchange = Exchange::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/transactions', [
        'portfolio_id' => $portfolio->id,
        'asset_id' => $asset->id,
        'exchange_id' => $exchange->id,
        'type' => Transaction::TYPE_BUY,
        'amount' => 0.5,
        'price_jpy' => 1_000_000,
        'fee_jpy' => 0,
        'executed_at' => now()->subHour()->toDateTimeString(),
        'note' => 'api',
    ])
        ->assertCreated()
        ->assertJsonPath('transaction.portfolio_id', $portfolio->id);

    $this->getJson('/api/v1/transactions')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

test('API: 他人の取引は更新できない', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $pOther = Portfolio::factory()->for($other)->create();
    $asset = Asset::factory()->create();
    $tx = Transaction::factory()->for($pOther)->for($asset)->create();

    $myP = Portfolio::factory()->for($me)->create();

    Sanctum::actingAs($me);

    $this->patchJson("/api/v1/transactions/{$tx->id}", [
        'portfolio_id' => $myP->id,
        'asset_id' => $asset->id,
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 100,
        'executed_at' => now()->subHour()->toDateTimeString(),
    ])->assertForbidden();
});
