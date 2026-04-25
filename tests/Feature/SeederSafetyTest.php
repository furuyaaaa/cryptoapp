<?php

use App\Models\Asset;
use App\Models\Exchange;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

test('DatabaseSeeder はマスタデータを投入する', function () {
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
        ->assertOk();

    expect(Asset::count())->toBeGreaterThan(0);
    expect(Exchange::count())->toBeGreaterThan(0);
});

test('本番環境では DemoSeeder は test@example.com を作成しない', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(app()->environment('production'))->toBeTrue();

    $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])
        ->assertOk();

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('本番環境で DatabaseSeeder を実行しても test@example.com は作られない', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
        ->assertOk();

    expect(Asset::count())->toBeGreaterThan(0);
    expect(User::where('email', 'test@example.com')->exists())->toBeFalse();
});

test('非本番環境では DatabaseSeeder がデモユーザーを自動投入する', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
        ->assertOk();

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});
