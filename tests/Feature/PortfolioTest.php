<?php

use App\Models\Portfolio;
use App\Models\User;

test('ゲストはポートフォリオ一覧にアクセスできない', function () {
    $this->get(route('portfolios.index'))
        ->assertRedirect(route('login'));
});

test('自分のポートフォリオのみ一覧に表示される', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    Portfolio::factory()->for($me)->create(['name' => 'My Wallet']);
    Portfolio::factory()->for($other)->create(['name' => 'Other Wallet']);

    $this->actingAs($me)
        ->get(route('portfolios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Portfolios/Index')
            ->has('portfolios', 1)
            ->where('portfolios.0.name', 'My Wallet')
        );
});

test('ポートフォリオを作成できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('portfolios.store'), [
            'name' => 'メインポートフォリオ',
            'description' => '長期保有用',
        ])
        ->assertRedirect(route('portfolios.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('portfolios', [
        'user_id' => $user->id,
        'name' => 'メインポートフォリオ',
    ]);
});

test('name が空だとバリデーションエラー', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('portfolios.create'))
        ->post(route('portfolios.store'), [
            'name' => '',
        ])
        ->assertRedirect(route('portfolios.create'))
        ->assertSessionHasErrors('name');
});

test('自分のポートフォリオを更新できる', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create(['name' => '旧']);

    $this->actingAs($user)
        ->put(route('portfolios.update', $portfolio), [
            'name' => '新',
            'description' => '更新',
        ])
        ->assertRedirect(route('portfolios.index'));

    expect($portfolio->fresh()->name)->toBe('新');
});

test('他ユーザーのポートフォリオは編集画面にアクセスできない', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $portfolio = Portfolio::factory()->for($other)->create();

    $this->actingAs($me)
        ->get(route('portfolios.edit', $portfolio))
        ->assertForbidden();
});

test('他ユーザーのポートフォリオは更新・削除できない', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $portfolio = Portfolio::factory()->for($other)->create(['name' => 'Other']);

    $this->actingAs($me)
        ->put(route('portfolios.update', $portfolio), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs($me)
        ->delete(route('portfolios.destroy', $portfolio))
        ->assertForbidden();

    expect($portfolio->fresh()->name)->toBe('Other');
});

test('自分のポートフォリオを削除できる', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('portfolios.destroy', $portfolio))
        ->assertRedirect(route('portfolios.index'));

    $this->assertDatabaseMissing('portfolios', ['id' => $portfolio->id]);
});
