<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\BitgetClient;
use App\Services\Exchanges\BitgetExecutionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateBitgetConnection extends Command
{
    protected $signature = 'bitget:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--symbol=ALL_USDT_SYMBOLS : Bitget spot symbol}
        {--key= : Bitget API Key}
        {--secret= : Bitget API Secret}
        {--passphrase= : Bitget API Passphrase}
        {--sync-start-date=today : today, all, or YYYY-MM-DD}
        {--skip-read-check : 読み取りAPIを呼ばずに保存する}';

    protected $description = 'Bitget の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'bitget'],
            ['name' => 'Bitget', 'country' => null],
        );

        $key = (string) ($this->option('key') ?: $this->secret('Bitget API Key'));
        $secret = (string) ($this->option('secret') ?: $this->secret('Bitget API Secret'));
        $passphrase = (string) ($this->option('passphrase') ?: $this->secret('Bitget API Passphrase'));
        $symbol = strtoupper((string) $this->option('symbol'));

        if ($symbol !== BitgetExecutionSyncService::ALL_USDT_SYMBOLS && ! str_ends_with($symbol, 'USDT')) {
            $this->error('Only ALL_USDT_SYMBOLS or USDT spot symbols are supported.');

            return self::FAILURE;
        }

        if (! $this->option('skip-read-check')) {
            (new BitgetClient($key, $secret, $passphrase, config('services.bitget.base_url')))
                ->assets('USDT');
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => $symbol,
            ],
            [
                'label' => 'Bitget '.$this->labelForSymbol($symbol),
                'api_key' => $key,
                'api_secret' => $secret,
                'api_passphrase' => $passphrase,
                'sync_start_at' => $this->syncStartAt(),
                'is_active' => true,
            ],
        );

        $this->info('Bitget connection saved.');

        return self::SUCCESS;
    }

    private function labelForSymbol(string $symbol): string
    {
        return $symbol === BitgetExecutionSyncService::ALL_USDT_SYMBOLS
            ? '全USDT建て現物'
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
