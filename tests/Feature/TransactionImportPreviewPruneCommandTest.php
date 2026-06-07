<?php

use Illuminate\Support\Facades\Storage;

function putTransactionImportPreviewFile(string $path, int $timestamp): void
{
    $disk = Storage::disk(config('filesystems.default'));

    $disk->put($path, "executed_at,type,symbol,amount,price_jpy\n2026-06-01 10:00:00,buy,BTC,0.1,10000000\n");
    touch($disk->path($path), $timestamp);
}

test('古いCSVインポートプレビューファイルだけ削除する', function () {
    $diskName = config('filesystems.default');
    Storage::fake($diskName);

    putTransactionImportPreviewFile('transaction-import-previews/old.csv', now()->subHours(25)->getTimestamp());
    putTransactionImportPreviewFile('transaction-import-previews/recent.csv', now()->subHours(2)->getTimestamp());
    putTransactionImportPreviewFile('transaction-import-previews/old.txt', now()->subHours(25)->getTimestamp());

    $this->artisan('transaction-import-previews:prune')
        ->expectsOutput('Deleted 1 stale transaction import preview file(s).')
        ->assertSuccessful();

    $disk = Storage::disk($diskName);

    expect($disk->exists('transaction-import-previews/old.csv'))->toBeFalse()
        ->and($disk->exists('transaction-import-previews/recent.csv'))->toBeTrue()
        ->and($disk->exists('transaction-import-previews/old.txt'))->toBeTrue();
});

test('dry-runではCSVインポートプレビューファイルを削除しない', function () {
    $diskName = config('filesystems.default');
    Storage::fake($diskName);

    putTransactionImportPreviewFile('transaction-import-previews/old.csv', now()->subHours(25)->getTimestamp());

    $this->artisan('transaction-import-previews:prune', ['--dry-run' => true])
        ->expectsOutput('Would delete 1 stale transaction import preview file(s).')
        ->assertSuccessful();

    expect(Storage::disk($diskName)->exists('transaction-import-previews/old.csv'))->toBeTrue();
});

test('CSVインポートプレビュー削除の保持時間は1時間以上にする', function () {
    $diskName = config('filesystems.default');
    Storage::fake($diskName);

    putTransactionImportPreviewFile('transaction-import-previews/old.csv', now()->subHours(25)->getTimestamp());

    $this->artisan('transaction-import-previews:prune', ['--hours' => 0])
        ->expectsOutput('--hours must be at least 1.')
        ->assertFailed();

    expect(Storage::disk($diskName)->exists('transaction-import-previews/old.csv'))->toBeTrue();
});
