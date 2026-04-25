<?php

use App\Models\Portfolio;
use App\Models\User;

/**
 * 名前付きレートリミッター (writes / exports / auth-post) の上限が 429 を返すことを固定化するテスト。
 *
 * 閾値はあくまで運用負荷の目安なので、将来の調整で落ちたらこのテスト側も調整する。
 */

test('writes リミッター: ポートフォリオ作成を 60/min を超えて呼ぶと 429 が返る', function () {
    $user = User::factory()->create();

    // 60件までは成功、61件目で 429
    for ($i = 0; $i < 60; $i++) {
        $response = $this->actingAs($user)
            ->post('/portfolios', ['name' => "PF {$i}"]);
        $response->assertRedirect();
    }

    $this->actingAs($user)
        ->post('/portfolios', ['name' => 'Boom'])
        ->assertStatus(429);
});

test('exports リミッター: 取引CSVエクスポートを 10/min を超えて呼ぶと 429 が返る', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($user)
            ->get('/transactions/export')
            ->assertOk();
    }

    $this->actingAs($user)
        ->get('/transactions/export')
        ->assertStatus(429);
});

test('auth-post リミッター: register を 5/min を超えて呼ぶと 429 が返る', function () {
    // 同じ IP (127.0.0.1) から 5回 POST までは許容、6回目で 429
    for ($i = 0; $i < 5; $i++) {
        $this->post('/register', [
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password' => 'password-1234',
            'password_confirmation' => 'password-1234',
        ])->assertRedirect();
    }

    $this->post('/register', [
        'name' => 'Overflow',
        'email' => 'overflow@example.com',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertStatus(429);
});

test('writes リミッター: 異なるユーザーは独立したバケットで制限される', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // user1 を上限まで使い切る
    for ($i = 0; $i < 60; $i++) {
        $this->actingAs($user1)
            ->post('/portfolios', ['name' => "U1 {$i}"]);
    }
    $this->actingAs($user1)->post('/portfolios', ['name' => 'blocked'])
        ->assertStatus(429);

    // user2 は影響を受けない
    $this->actingAs($user2)
        ->post('/portfolios', ['name' => 'ok'])
        ->assertRedirect();
});
