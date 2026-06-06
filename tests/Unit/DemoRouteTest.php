<?php

use Tests\TestCase;

uses(TestCase::class);

test('デモ画面は未ログインでも表示できる', function () {
    $this->withoutVite();

    $this->get(route('demo'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('auth.user.email', 'demo@example.com')
            ->where('totals.valuation', 12_450_780)
            ->has('allocation', 6)
            ->has('topHoldings', 5)
            ->has('recentTransactions', 5)
        );
});
