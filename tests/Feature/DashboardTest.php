<?php

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;

test('ゲストはダッシュボードにアクセスできず、ログイン画面へリダイレクトされる', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('ログイン済みユーザーはダッシュボードを表示できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('totals')
            ->has('allocation')
            ->has('topHoldings')
            ->has('recentTransactions')
        );
});

test('保有銘柄がある場合、集計値と資産構成が返される', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();

    $btc = Asset::factory()->create(['symbol' => 'BTC']);
    AssetPrice::factory()->for($btc)->create([
        'price_jpy' => 10_000_000,
        'recorded_at' => now(),
    ]);

    Transaction::factory()->for($portfolio)->for($btc)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 5_000_000,
        'fee_jpy' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.valuation', 10_000_000)
            ->where('totals.cost_basis', 5_000_000)
            ->where('totals.profit', 5_000_000)
            ->where('totals.portfolios_count', 1)
            ->where('totals.assets_count', 1)
            ->where('totals.transactions_count', 1)
            ->has('allocation', 1)
            ->where('allocation.0.symbol', 'BTC')
            ->has('recentTransactions', 1)
        );
});

test('他ユーザーのポートフォリオ・取引はダッシュボードに表示されない', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $otherPortfolio = Portfolio::factory()->for($other)->create();
    $asset = Asset::factory()->create();
    AssetPrice::factory()->for($asset)->create(['price_jpy' => 1000]);
    Transaction::factory()->for($otherPortfolio)->for($asset)->create();

    $this->actingAs($me)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totals.portfolios_count', 0)
            ->where('totals.transactions_count', 0)
            ->has('allocation', 0)
            ->has('recentTransactions', 0)
        );
});
