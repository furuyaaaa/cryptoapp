<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('coingecko:fetch-asset-prices')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('bitflyer:sync-executions')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('bitbank:sync-executions')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('coincheck:sync-executions')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('gmo-coin:sync-executions')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('zaif:sync-executions')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('binance:sync-executions')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
