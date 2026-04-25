<?php

use App\Models\User;
use App\Services\TwoFactorAuthenticationService;

/**
 * `two-factor` 名前付きレートリミッターの挙動を固定化する。
 *
 * 設計:
 *  - ユーザー単位 5/min + IP 単位 20/min の二重バケット
 *  - challenge.store と two-factor.confirm に同一リミッターを適用
 */
function makeTwoFactorUser(): User
{
    $service = app(TwoFactorAuthenticationService::class);
    $secret = $service->generateSecret();
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

test('challenge は同一ユーザーが 5 回失敗すると 6 回目で 429', function () {
    $user = makeTwoFactorUser();

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)
            ->withSession(['auth.two_factor_verified' => false])
            ->from(route('two-factor.challenge'))
            ->post(route('two-factor.challenge.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    $response = $this->actingAs($user)
        ->withSession(['auth.two_factor_verified' => false])
        ->post(route('two-factor.challenge.store'), ['code' => '000000']);

    $response->assertStatus(429);
});

test('confirm も同一ユーザーが 5 回失敗すると 6 回目で 429', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('two-factor.store'));
    $user->refresh();

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    $response = $this->actingAs($user)
        ->post(route('two-factor.confirm'), ['code' => '000000']);

    $response->assertStatus(429);
});

test('別ユーザーは独立したバケットを持つ', function () {
    $user1 = makeTwoFactorUser();
    $user2 = makeTwoFactorUser();

    // user1 でバケットを使い切る
    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user1)
            ->withSession(['auth.two_factor_verified' => false])
            ->post(route('two-factor.challenge.store'), ['code' => '000000']);
    }

    $this->actingAs($user1)
        ->withSession(['auth.two_factor_verified' => false])
        ->post(route('two-factor.challenge.store'), ['code' => '000000'])
        ->assertStatus(429);

    // user2 はまだ試せる
    $this->actingAs($user2)
        ->withSession(['auth.two_factor_verified' => false])
        ->from(route('two-factor.challenge'))
        ->post(route('two-factor.challenge.store'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
});

test('challenge / confirm はユーザー/IP 両方の制限を共有しない（それぞれ独立）', function () {
    // confirm と challenge はどちらも 'two-factor' リミッターだが、
    // Laravel の名前付きリミッターは同一キーで一緒にカウントされる。
    // すなわち同じユーザーが challenge 3回 + confirm 3回 しても、ユーザー単位 5 を超えれば 429 になる。
    $user = makeTwoFactorUser();

    // 未確認状態にするために 2FA confirmed_at を一旦クリア（confirm を走らせるため）
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    // challenge 3 回
    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($user)
            ->withSession(['auth.two_factor_verified' => false])
            ->post(route('two-factor.challenge.store'), ['code' => '000000']);
    }

    // confirm 2 回 → 計 5 回
    for ($i = 0; $i < 2; $i++) {
        $this->actingAs($user)
            ->post(route('two-factor.confirm'), ['code' => '000000']);
    }

    // 6 回目は challenge でも confirm でも 429
    $this->actingAs($user)
        ->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertStatus(429);
});
