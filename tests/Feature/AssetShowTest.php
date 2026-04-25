<?php

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;

test('ゲストは銘柄詳細にアクセスできない', function () {
    $asset = Asset::factory()->create(['symbol' => 'BTC']);

    $this->get(route('assets.show', $asset->symbol))
        ->assertRedirect(route('login'));
});

test('存在しないシンボルは 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('assets.show', 'XXX'))
        ->assertNotFound();
});

test('小文字のシンボルでも大文字に変換されて解決される', function () {
    $user = User::factory()->create();
    Asset::factory()->create(['symbol' => 'BTC']);

    $this->actingAs($user)
        ->get(route('assets.show', 'btc'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assets/Show')
            ->where('asset.symbol', 'BTC')
        );
});

test('自分の取引のみが表示され、保有集計が正しく返される', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $myPortfolio = Portfolio::factory()->for($me)->create();
    $otherPortfolio = Portfolio::factory()->for($other)->create();

    $btc = Asset::factory()->create(['symbol' => 'BTC']);
    AssetPrice::factory()->for($btc)->create([
        'price_jpy' => 10_000_000,
        'price_usd' => 70_000,
        'recorded_at' => now(),
    ]);

    Transaction::factory()->for($myPortfolio)->for($btc)->buy()->create([
        'amount' => 2,
        'price_jpy' => 5_000_000,
        'fee_jpy' => 0,
        'executed_at' => now()->subDays(10),
    ]);
    Transaction::factory()->for($myPortfolio)->for($btc)->sell()->create([
        'amount' => 1,
        'price_jpy' => 8_000_000,
        'fee_jpy' => 0,
        'executed_at' => now()->subDays(5),
    ]);

    Transaction::factory()->for($otherPortfolio)->for($btc)->buy()->create();

    $this->actingAs($me)
        ->get(route('assets.show', 'BTC'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assets/Show')
            ->where('asset.symbol', 'BTC')
            ->has('transactions', 2)
            ->where('holding.amount', 1)
            ->where('holding.avg_buy_price', 5_000_000)
            ->where('holding.cost_basis', 5_000_000)
            ->where('holding.valuation', 10_000_000)
            ->where('holding.realized_profit', 3_000_000)
        );
});

test('range クエリで価格履歴の範囲が絞られる', function () {
    $user = User::factory()->create();
    $btc = Asset::factory()->create(['symbol' => 'BTC']);

    AssetPrice::factory()->for($btc)->create([
        'price_jpy' => 1,
        'price_usd' => 1,
        'recorded_at' => now()->subDays(60),
    ]);
    AssetPrice::factory()->for($btc)->create([
        'price_jpy' => 2,
        'price_usd' => 2,
        'recorded_at' => now()->subDays(3),
    ]);

    $this->actingAs($user)
        ->get(route('assets.show', 'BTC').'?range=7d')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range', '7d')
            ->has('prices', 1)
        );

    $this->actingAs($user)
        ->get(route('assets.show', 'BTC').'?range=all')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('prices', 2)
        );
});
