<?php

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TaxCalculationService;

test('ゲストは税務ページにアクセスできない', function () {
    $this->get(route('tax.index'))
        ->assertRedirect(route('login'));
});

test('取引が無くても税務ページが表示される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tax.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tax/Index')
            ->has('report.totals')
            ->where('report.totals.realized_gain', 0)
        );
});

test('移動平均法で実現損益を正しく計算する', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create(['symbol' => 'BTC']);

    // 2026年内: 1BTC@100万円で購入、1BTC@200万円で購入、1BTC@400万円で売却
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 1_000_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-01-10 10:00:00',
    ]);
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 2_000_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-02-10 10:00:00',
    ]);
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_SELL,
        'amount' => 1,
        'price_jpy' => 4_000_000,
        'fee_jpy' => 1_000,
        'executed_at' => '2026-06-10 10:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('tax.index', ['year' => 2026, 'method' => 'moving_average']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tax/Index')
            // 平均単価は (100万 + 200万) / 2 = 150万円
            // 譲渡原価 = 150万 × 1 = 150万円
            // 実現損益 = 400万 − 150万 − 1,000 = 2,499,000円
            ->where('report.totals.realized_gain', 2_499_000)
            ->where('report.totals.proceeds', 4_000_000)
            ->where('report.totals.cost_of_sold', 1_500_000)
            ->where('report.totals.sell_count', 1)
        );
});

test('総平均法で実現損益を正しく計算する', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create(['symbol' => 'ETH']);

    // 2026年: 2ETH@10万、3ETH@20万で購入、1ETH@40万で売却
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 2,
        'price_jpy' => 100_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-01-10 10:00:00',
    ]);
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 3,
        'price_jpy' => 200_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-03-10 10:00:00',
    ]);
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_SELL,
        'amount' => 1,
        'price_jpy' => 400_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-08-10 10:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('tax.index', ['year' => 2026, 'method' => 'total_average']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tax/Index')
            // 平均単価 = (2×10万 + 3×20万) / (2+3) = 80万/5 = 16万円
            // 譲渡原価 = 16万 × 1 = 16万円
            // 実現損益 = 40万 − 16万 = 24万円
            ->where('report.totals.realized_gain', 240_000)
            ->where('report.totals.proceeds', 400_000)
            ->where('report.totals.cost_of_sold', 160_000)
        );
});

test('前年からの在庫を引き継いで計算する（移動平均法）', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create(['symbol' => 'BTC']);

    // 2025年: 1BTC@100万で購入（期首在庫として2026年に引き継ぎ）
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 1_000_000,
        'fee_jpy' => 0,
        'executed_at' => '2025-06-01 10:00:00',
    ]);
    // 2026年: 1BTC@300万で売却
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_SELL,
        'amount' => 1,
        'price_jpy' => 3_000_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-02-01 10:00:00',
    ]);

    $service = app(TaxCalculationService::class);
    $report = $service->calculate(
        Transaction::with('asset')->get(),
        2026,
        TaxCalculationService::METHOD_MOVING_AVERAGE,
    );

    expect($report['totals']['realized_gain'])->toEqual(2_000_000.0);
    expect($report['assets'][0]['opening_amount'])->toEqual(1.0);
    expect($report['assets'][0]['opening_cost'])->toEqual(1_000_000.0);
});

test('移動平均法と総平均法で結果が異なることを確認', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create(['symbol' => 'BTC']);

    // 1BTC@100万、1BTC売却@200万、1BTC@300万購入（移動平均と総平均で差が出るケース）
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 1_000_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-01-10 10:00:00',
    ]);
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_SELL,
        'amount' => 1,
        'price_jpy' => 2_000_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-03-10 10:00:00',
    ]);
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 3_000_000,
        'fee_jpy' => 0,
        'executed_at' => '2026-06-10 10:00:00',
    ]);

    $service = app(TaxCalculationService::class);
    $txs = Transaction::with('asset')->get();

    $mov = $service->calculate($txs, 2026, TaxCalculationService::METHOD_MOVING_AVERAGE);
    // 移動平均法: 売却時点の単価=100万 → 実現損益 = 200万 − 100万 = 100万円
    expect($mov['totals']['realized_gain'])->toEqual(1_000_000.0);

    $tot = $service->calculate($txs, 2026, TaxCalculationService::METHOD_TOTAL_AVERAGE);
    // 総平均法: 平均単価 = (100万 + 300万) / 2 = 200万円
    // 譲渡原価 = 200万 × 1 = 200万円 → 実現損益 = 200万 − 200万 = 0円
    expect($tot['totals']['realized_gain'])->toEqual(0.0);
});

test('CSVエクスポートが動作する', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    $asset = Asset::factory()->create(['symbol' => 'BTC']);

    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 1_000_000,
        'executed_at' => '2026-01-10 10:00:00',
    ]);
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_SELL,
        'amount' => 1,
        'price_jpy' => 2_000_000,
        'executed_at' => '2026-06-10 10:00:00',
    ]);

    $response = $this->actingAs($user)
        ->get(route('tax.export', ['year' => 2026, 'method' => 'moving_average']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())
        ->toContain('BTC')
        ->toContain('1000000.00');
});

test('税務CSVエクスポートで数式インジェクションがエスケープされる', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create();
    // 銘柄名に数式注入を仕込む
    $asset = Asset::factory()->create([
        'symbol' => 'BTC',
        'name' => '=cmd|evil',
    ]);

    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_BUY,
        'amount' => 1,
        'price_jpy' => 1_000_000,
        'executed_at' => '2026-01-10 10:00:00',
    ]);
    Transaction::factory()->for($portfolio)->for($asset)->create([
        'type' => Transaction::TYPE_SELL,
        'amount' => 1,
        'price_jpy' => 2_000_000,
        'executed_at' => '2026-06-10 10:00:00',
    ]);

    $response = $this->actingAs($user)
        ->get(route('tax.export', ['year' => 2026, 'method' => 'moving_average']));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain("'=cmd|evil");
    expect($csv)->not->toMatch('/(^|,|")=cmd\|evil/m');
});

test('自分の取引のみが計算対象になる', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $asset = Asset::factory()->create();

    $myPortfolio = Portfolio::factory()->for($me)->create();
    $otherPortfolio = Portfolio::factory()->for($other)->create();

    Transaction::factory()->for($otherPortfolio)->for($asset)->create([
        'type' => Transaction::TYPE_SELL,
        'amount' => 1,
        'price_jpy' => 5_000_000,
        'executed_at' => '2026-06-10 10:00:00',
    ]);

    $this->actingAs($me)
        ->get(route('tax.index', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('report.totals.realized_gain', 0)
            ->where('report.totals.sell_count', 0)
        );
});
