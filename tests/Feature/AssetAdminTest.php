<?php

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;

test('ゲストは銘柄管理画面にアクセスできない', function () {
    $this->get(route('assets.index'))
        ->assertRedirect(route('login'));
});

test('管理者ではないユーザーは銘柄管理画面にアクセスできない', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('assets.index'))
        ->assertForbidden();
});

test('管理者は銘柄一覧を表示できる', function () {
    $admin = User::factory()->admin()->create();
    Asset::factory()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);
    Asset::factory()->create(['symbol' => 'ETH', 'name' => 'Ethereum']);

    $this->actingAs($admin)
        ->get(route('assets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assets/Index')
            ->has('assets.data', 2)
        );
});

test('管理者は銘柄を検索できる', function () {
    $admin = User::factory()->admin()->create();
    Asset::factory()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);
    Asset::factory()->create(['symbol' => 'ETH', 'name' => 'Ethereum']);

    $this->actingAs($admin)
        ->get(route('assets.index', ['q' => 'bitcoin']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('assets.data', 1)
            ->where('assets.data.0.symbol', 'BTC')
        );
});

test('管理者は銘柄を新規登録できる', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('assets.store'), [
            'symbol' => 'sol',
            'name' => 'Solana',
            'coingecko_id' => 'Solana',
            'icon_url' => 'https://example.com/sol.png',
        ])
        ->assertRedirect(route('assets.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('assets', [
        'symbol' => 'SOL',
        'name' => 'Solana',
        'coingecko_id' => 'solana',
        'icon_url' => 'https://example.com/sol.png',
    ]);
});

test('管理者ではないユーザーは銘柄を作成できない', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('assets.store'), [
            'symbol' => 'SOL',
            'name' => 'Solana',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('assets', ['symbol' => 'SOL']);
});

test('シンボルが空だとバリデーションエラー', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('assets.create'))
        ->post(route('assets.store'), [
            'symbol' => '',
            'name' => 'Test',
        ])
        ->assertRedirect(route('assets.create'))
        ->assertSessionHasErrors('symbol');
});

test('同じシンボルは重複登録できない', function () {
    $admin = User::factory()->admin()->create();
    Asset::factory()->create(['symbol' => 'BTC']);

    $this->actingAs($admin)
        ->from(route('assets.create'))
        ->post(route('assets.store'), [
            'symbol' => 'BTC',
            'name' => 'Another Bitcoin',
        ])
        ->assertRedirect(route('assets.create'))
        ->assertSessionHasErrors('symbol');
});

test('シンボルの不正な文字はバリデーションエラー', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('assets.create'))
        ->post(route('assets.store'), [
            'symbol' => 'Bit coin!',
            'name' => 'Test',
        ])
        ->assertRedirect(route('assets.create'))
        ->assertSessionHasErrors('symbol');
});

test('管理者は銘柄編集画面を表示できる', function () {
    $admin = User::factory()->admin()->create();
    $asset = Asset::factory()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);

    $this->actingAs($admin)
        ->get(route('assets.edit', $asset))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assets/Edit')
            ->where('asset.symbol', 'BTC')
        );
});

test('管理者は銘柄を更新できる', function () {
    $admin = User::factory()->admin()->create();
    $asset = Asset::factory()->create([
        'symbol' => 'BTC',
        'name' => 'Bitcoin',
        'coingecko_id' => 'bitcoin',
    ]);

    $this->actingAs($admin)
        ->put(route('assets.update', $asset), [
            'symbol' => 'BTC',
            'name' => 'Bitcoin (updated)',
            'coingecko_id' => 'bitcoin',
            'icon_url' => 'https://example.com/btc.png',
        ])
        ->assertRedirect(route('assets.index'))
        ->assertSessionHas('success');

    expect($asset->fresh()->name)->toBe('Bitcoin (updated)');
    expect($asset->fresh()->icon_url)->toBe('https://example.com/btc.png');
});

test('取引履歴のない銘柄は削除できる', function () {
    $admin = User::factory()->admin()->create();
    $asset = Asset::factory()->create();

    $this->actingAs($admin)
        ->delete(route('assets.destroy', $asset))
        ->assertRedirect(route('assets.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
});

test('取引履歴のある銘柄は削除できない', function () {
    $admin = User::factory()->admin()->create();
    $asset = Asset::factory()->create();
    $portfolio = Portfolio::factory()->for($admin)->create();
    Transaction::factory()
        ->for($portfolio)
        ->for($asset)
        ->create();

    $this->actingAs($admin)
        ->delete(route('assets.destroy', $asset))
        ->assertRedirect(route('assets.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('assets', ['id' => $asset->id]);
});

test('管理者ではないユーザーは銘柄を更新・削除できない', function () {
    $user = User::factory()->create();
    $asset = Asset::factory()->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('assets.update', $asset), [
            'symbol' => $asset->symbol,
            'name' => 'Hacked',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('assets.destroy', $asset))
        ->assertForbidden();

    expect($asset->fresh()->name)->toBe('Original');
});
