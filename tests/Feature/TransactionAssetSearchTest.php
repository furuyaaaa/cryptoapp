<?php

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\User;

test('認証済みユーザーは取引フォーム用の銘柄検索 JSON を取得できる', function () {
    $user = User::factory()->create();
    Portfolio::factory()->for($user)->create();
    Asset::factory()->create(['symbol' => 'SRCH', 'name' => 'Searchable Token', 'coingecko_id' => 'searchable']);

    $this->actingAs($user)
        ->getJson(route('transactions.assets.search', ['q' => 'SRCH']))
        ->assertOk()
        ->assertJsonPath('data.0.symbol', 'SRCH');
});

test('ゲストは銘柄検索にアクセスできない', function () {
    $this->getJson(route('transactions.assets.search'))
        ->assertUnauthorized();
});
