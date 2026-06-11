<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\KuCoinClient;
use App\Services\Exchanges\KuCoinExecutionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateKuCoinConnection extends Command
{
    protected $signature = 'kucoin:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--symbol=ALL_KUCOIN_USDT_SYMBOLS : KuCoin spot symbol}
        {--key= : KuCoin API Key}
        {--secret= : KuCoin API Secret}
        {--passphrase= : KuCoin API Passphrase}
        {--sync-start-date=today : today, all, or YYYY-MM-DD}
        {--skip-read-check : 読み取りAPIを呼ばずに保存する}';

    protected $description = 'KuCoin の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'kucoin'],
            ['name' => 'KuCoin', 'country' => null],
        );

        $key = (string) ($this->option('key') ?: $this->secret('KuCoin API Key'));
        $secret = (string) ($this->option('secret') ?: $this->secret('KuCoin API Secret'));
        $passphrase = (string) ($this->option('passphrase') ?: $this->secret('KuCoin API Passphrase'));
        $symbol = strtoupper((string) $this->option('symbol'));

        if ($symbol !== KuCoinExecutionSyncService::ALL_USDT_SYMBOLS && ! str_ends_with($symbol, '-USDT')) {
            $this->error('Only ALL_KUCOIN_USDT_SYMBOLS or USDT spot symbols are supported.');

            return self::FAILURE;
        }

        if (! $this->option('skip-read-check')) {
            (new KuCoinClient($key, $secret, $passphrase, config('services.kucoin.base_url')))
                ->apiKeyInfo();
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => $symbol,
            ],
            [
                'label' => 'KuCoin '.$this->labelForSymbol($symbol),
                'api_key' => $key,
                'api_secret' => $secret,
                'api_passphrase' => $passphrase,
                'sync_start_at' => $this->syncStartAt(),
                'is_active' => true,
            ],
        );

        $this->info('KuCoin connection saved.');

        return self::SUCCESS;
    }

    private function labelForSymbol(string $symbol): string
    {
        return $symbol === KuCoinExecutionSyncService::ALL_USDT_SYMBOLS
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
