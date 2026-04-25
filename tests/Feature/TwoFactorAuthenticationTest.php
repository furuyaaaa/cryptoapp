<?php

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use PragmaRX\Google2FA\Google2FA;

/**
 * 2FA (TOTP) 一連のフロー:
 *  - /profile/two-factor (POST)         : secret 発行
 *  - /profile/two-factor/confirm (POST) : 6桁コードで確認
 *  - /two-factor-challenge (GET/POST)   : ログイン後のチャレンジ
 *  - /profile/two-factor (DELETE)       : 解除（password 確認が必要）
 *
 * を検証する。
 */
function currentOtp(string $secret): string
{
    return (new Google2FA())->getCurrentOtp($secret);
}

test('2FA を有効化していないユーザーは素通しでダッシュボードに入れる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

test('2FA 有効化済みユーザーは challenge を通さないと dashboard にアクセスできない', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->withSession(['auth.two_factor_verified' => false])
        ->get('/dashboard')
        ->assertRedirect(route('two-factor.challenge'));
});

test('有効化フロー: secret を払い出し、正しいコードで confirm すると有効化され復旧コードが発行される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile/two-factor')
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();

    $otp = currentOtp($user->two_factor_secret);

    $this->actingAs($user)
        ->post('/profile/two-factor/confirm', ['code' => $otp])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('recoveryCodes');

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeTrue();
    expect($user->two_factor_recovery_codes)->toHaveCount(8);
});

test('confirm は不正な 6 桁コードを拒否する', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/profile/two-factor');
    $user->refresh();

    $this->actingAs($user)
        ->from('/profile')
        ->post('/profile/two-factor/confirm', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('challenge: 正しい TOTP を送ると dashboard にリダイレクトされる', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $otp = currentOtp($secret);

    $this->actingAs($user)
        ->post('/two-factor-challenge', ['code' => $otp])
        ->assertRedirect();

    // チャレンジ後は dashboard に入れる
    $this->actingAs($user)
        ->withSession(['auth.two_factor_verified' => true])
        ->get('/dashboard')
        ->assertOk();
});

test('challenge: 不正な TOTP は 拒否する', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->from(route('two-factor.challenge'))
        ->post('/two-factor-challenge', ['code' => '123456'])
        ->assertSessionHasErrors('code');
});

test('challenge: 復旧コードで通過でき、使用したコードは消費される', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $codes = $service->generateRecoveryCodes();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $codes,
        'two_factor_confirmed_at' => now(),
    ])->save();

    $used = $codes[0];

    $this->actingAs($user)
        ->post('/two-factor-challenge', ['recovery_code' => $used])
        ->assertRedirect();

    $remaining = $user->refresh()->two_factor_recovery_codes;
    expect($remaining)->toHaveCount(7);
    expect($remaining)->not->toContain($used);
});

test('challenge: 存在しない復旧コードは 拒否する', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->from(route('two-factor.challenge'))
        ->post('/two-factor-challenge', ['recovery_code' => 'not-a-real-code'])
        ->assertSessionHasErrors('recovery_code');
});

test('無効化: 2FA を削除するとカラムがクリアされる', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    // password.confirm ミドルウェアの確認済みフラグをセッションに入れて疎通させる。
    $this->actingAs($user)
        ->withSession([
            'auth.two_factor_verified' => true,
            'auth.password_confirmed_at' => time(),
        ])
        ->delete('/profile/two-factor')
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull();
    expect($user->two_factor_recovery_codes)->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
    expect($user->hasTwoFactorEnabled())->toBeFalse();
});

test('2FA 有効ユーザーは challenge 画面自体には入れる', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->withSession(['auth.two_factor_verified' => false])
        ->get('/two-factor-challenge')
        ->assertOk();
});

test('復旧コードの再発行は 8 件の新しい配列を flash する', function () {
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $originalCodes = $service->generateRecoveryCodes();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $originalCodes,
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($user)
        ->withSession(['auth.two_factor_verified' => true])
        ->post('/profile/two-factor/recovery-codes')
        ->assertSessionHas('recoveryCodes');

    $new = $user->refresh()->two_factor_recovery_codes;
    expect($new)->toHaveCount(8);
    expect($new)->not->toEqual($originalCodes);
});

test('2FA を有効化していないユーザーが challenge にアクセスすると dashboard に飛ばされる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/two-factor-challenge')
        ->assertRedirect(route('dashboard'));
});
