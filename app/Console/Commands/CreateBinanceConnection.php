<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\BinanceClient;
use App\Services\Exchanges\BinanceExecutionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateBinanceConnection extends Command
{
    protected $signature = 'binance:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--symbol=ALL_JPY_SYMBOLS : Binance spot symbol}
        {--key= : Binance API Key}
        {--secret= : Binance API Secret}
        {--sync-start-date=today : today, all, or YYYY-MM-DD}
        {--skip-read-check : 読み取りAPIを呼ばずに保存する}';

    protected $description = 'Binance Japan の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'binance'],
            ['name' => 'Binance Japan', 'country' => 'JP'],
        );

        $key = (string) ($this->option('key') ?: $this->secret('Binance API Key'));
        $secret = (string) ($this->option('secret') ?: $this->secret('Binance API Secret'));
        $symbol = strtoupper((string) $this->option('symbol'));

        if ($symbol !== BinanceExecutionSyncService::ALL_JPY_SYMBOLS && ! str_ends_with($symbol, 'JPY')) {
            $this->error('Only ALL_JPY_SYMBOLS or JPY spot symbols are supported.');

            return self::FAILURE;
        }

        if (! $this->option('skip-read-check')) {
            (new BinanceClient($key, $secret, config('services.binance.base_url')))
                ->account();
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => $symbol,
            ],
            [
                'label' => 'Binance Japan '.$this->labelForSymbol($symbol),
                'api_key' => $key,
                'api_secret' => $secret,
                'sync_start_at' => $this->syncStartAt(),
                'is_active' => true,
            ],
        );

        $this->info('Binance Japan connection saved.');

        return self::SUCCESS;
    }

    private function labelForSymbol(string $symbol): string
    {
        return $symbol === BinanceExecutionSyncService::ALL_JPY_SYMBOLS
            ? '全JPY建て現物'
            : $symbol;
    }

    private function syncStartAt(): ?Carbon
    {
        $value = (string) $this->option('sync-start-date');

        if ($value === 'all') {
            return null;
        }

        return ($value === 'today' ? today() : Carbon::createFromFormat('Y-m-d', $value))->startOfDay();
    }
}
