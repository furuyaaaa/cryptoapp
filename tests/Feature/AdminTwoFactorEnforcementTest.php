<?php

use App\Models\User;

/**
 * 管理者は 2FA 必須 (EnsureAdminHasTwoFactor ミドルウェア) の挙動を固定化する。
 *
 * 要件:
 *  - 2FA 未設定の admin は admin 画面にアクセスできず /profile に誘導される
 *  - プロフィール画面自体には入れる（2FA をそこで設定できる必要がある）
 *  - 2FA を設定済みの admin は通常通り admin 画面にアクセスできる
 *  - 一般ユーザーの admin アクセスは従来どおり 403
 */
test('2FA 未設定の管理者は admin 画面にアクセスすると /profile に誘導される', function () {
    // admin() ファクトリーはデフォルトで 2FA を有効化するため、ここでは手動で無効化したadminを作成
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->hasTwoFactorEnabled())->toBeFalse();

    $this->actingAs($admin)
        ->get(route('assets.index'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('error');
});

test('2FA 未設定の管理者でもプロフィール画面には入れる（2FA を設定するため）', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertOk();
});

test('2FA 設定済みの管理者は admin 画面にアクセスできる', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->hasTwoFactorEnabled())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('assets.index'))
        ->assertOk();
});

test('一般ユーザーは 2FA の有無にかかわらず admin 画面は 403', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('assets.index'))
        ->assertForbidden();
});

test('2FA 未設定の管理者は admin 配下の書き込み系エンドポイントにも到達できない', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)
        ->post(route('assets.store'), [
            'symbol' => 'FOO',
            'name' => 'Foo',
        ])
        ->assertRedirect(route('profile.edit'));

    $this->assertDatabaseMissing('assets', ['symbol' => 'FOO']);
});
