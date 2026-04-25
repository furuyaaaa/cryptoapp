<?php

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Policies\AssetPolicy;
use App\Policies\PortfolioPolicy;
use App\Policies\TransactionPolicy;

/**
 * Policy 単体テスト。
 *
 * コントローラー経由で検証している Feature テストと冗長だが、
 * Policy の挙動を個別に固定化しておくことで将来のリファクタリング時に崩れにくくする。
 */

test('PortfolioPolicy: 所有者のみ view/update/delete できる', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $portfolio = Portfolio::factory()->for($owner)->create();
    $policy = new PortfolioPolicy();

    expect($policy->viewAny($owner))->toBeTrue();
    expect($policy->create($owner))->toBeTrue();

    expect($policy->view($owner, $portfolio))->toBeTrue();
    expect($policy->update($owner, $portfolio))->toBeTrue();
    expect($policy->delete($owner, $portfolio))->toBeTrue();

    expect($policy->view($stranger, $portfolio))->toBeFalse();
    expect($policy->update($stranger, $portfolio))->toBeFalse();
    expect($policy->delete($stranger, $portfolio))->toBeFalse();
});

test('TransactionPolicy: ポートフォリオ所有者のみ view/update/delete できる', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $portfolio = Portfolio::factory()->for($owner)->create();
    $asset = Asset::factory()->create();
    $transaction = Transaction::factory()
        ->for($portfolio)
        ->for($asset)
        ->create();

    $policy = new TransactionPolicy();

    expect($policy->viewAny($owner))->toBeTrue();
    expect($policy->create($owner))->toBeTrue();

    expect($policy->view($owner, $transaction))->toBeTrue();
    expect($policy->update($owner, $transaction))->toBeTrue();
    expect($policy->delete($owner, $transaction))->toBeTrue();

    expect($policy->view($stranger, $transaction))->toBeFalse();
    expect($policy->update($stranger, $transaction))->toBeFalse();
    expect($policy->delete($stranger, $transaction))->toBeFalse();
});

test('TransactionPolicy: portfolio リレーションがロード済みでもそうでなくても判定が一致する', function () {
    $owner = User::factory()->create();
    $portfolio = Portfolio::factory()->for($owner)->create();
    $asset = Asset::factory()->create();
    $transaction = Transaction::factory()->for($portfolio)->for($asset)->create();

    $policy = new TransactionPolicy();

    // 未ロード（DBから value 取得）
    $fresh = Transaction::find($transaction->id);
    expect($fresh->relationLoaded('portfolio'))->toBeFalse();
    expect($policy->update($owner, $fresh))->toBeTrue();

    // ロード済み
    $fresh->load('portfolio');
    expect($fresh->relationLoaded('portfolio'))->toBeTrue();
    expect($policy->update($owner, $fresh))->toBeTrue();
});

test('AssetPolicy: viewAny/create/update/delete は管理者のみ、view は認証済みなら誰でも可', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $asset = Asset::factory()->create();

    $policy = new AssetPolicy();

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->create($admin))->toBeTrue();
    expect($policy->update($admin, $asset))->toBeTrue();
    expect($policy->delete($admin, $asset))->toBeTrue();
    expect($policy->view($admin, $asset))->toBeTrue();

    expect($policy->viewAny($user))->toBeFalse();
    expect($policy->create($user))->toBeFalse();
    expect($policy->update($user, $asset))->toBeFalse();
    expect($policy->delete($user, $asset))->toBeFalse();
    expect($policy->view($user, $asset))->toBeTrue();
});
