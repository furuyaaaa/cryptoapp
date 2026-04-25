<?php

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;

/**
 * 検索クエリにワイルドカード文字 `%` `_` が入っても、DB レベルでワイルドカードとして
 * 解釈されないことを確認する（LIKE/ILIKE 乱用によるデータ無差別取得の防止）。
 */
test('Asset 検索: % を含む入力は % そのものにマッチする (LIKE ワイルドカード化しない)', function () {
    $admin = User::factory()->admin()->create();

    Asset::factory()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);
    Asset::factory()->create(['symbol' => 'ETH', 'name' => 'Ethereum']);
    Asset::factory()->create(['symbol' => 'XX1', 'name' => 'Test % coin']);

    // 入力 "%" が素通しだったら BTC/ETH 含めて全件ヒットしてしまう。
    // エスケープされていれば、`% coin` を含む「Test % coin」のみヒット。
    $response = $this->actingAs($admin)
        ->get(route('assets.index', ['q' => '%']))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->has('assets.data', 1)
        ->where('assets.data.0.symbol', 'XX1')
    );
});

test('Asset 検索: _ を含む入力は _ そのものにマッチする', function () {
    $admin = User::factory()->admin()->create();

    Asset::factory()->create(['symbol' => 'BTC', 'name' => 'Bitcoin']);
    Asset::factory()->create(['symbol' => 'ETH', 'name' => 'Ethereum']);
    Asset::factory()->create(['symbol' => 'ZZZ', 'name' => 'under_coin']);

    // 入力 "_" が素通しだったら、任意の 1 文字にマッチして BTC 等も拾う。
    $response = $this->actingAs($admin)
        ->get(route('assets.index', ['q' => '_']))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->has('assets.data', 1)
        ->where('assets.data.0.symbol', 'ZZZ')
    );
});

test('Transaction 検索: % を含む note 検索でワイルドカード化しない', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create(['symbol' => 'BTC']);

    Transaction::factory()
        ->for($portfolio)
        ->for($asset)
        ->create(['note' => '半額セール 50%OFF']);
    Transaction::factory()
        ->for($portfolio)
        ->for($asset)
        ->create(['note' => '通常購入']);

    $response = $this->actingAs($user)
        ->get(route('transactions.index', ['q' => '50%']))
        ->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->has('transactions.data', 1)
    );
});
