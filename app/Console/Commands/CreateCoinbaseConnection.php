<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\CoinbaseClient;
use App\Services\Exchanges\CoinbaseExecutionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateCoinbaseConnection extends Command
{
    protected $signature = 'coinbase:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--product=ALL_COINBASE_STABLE_QUOTE_PRODUCTS : Coinbase product id}
        {--key= : Coinbase CDP API Key name}
        {--secret= : Coinbase CDP API Secret PEM}
        {--sync-start-date=today : today, all, or YYYY-MM-DD}
        {--skip-read-check : 読み取りAPIを呼ばずに保存する}';

    protected $description = 'Coinbase Advanced Trade の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'coinbase'],
            ['name' => 'Coinbase', 'country' => 'US'],
        );

        $key = (string) ($this->option('key') ?: $this->ask('Coinbase CDP API Key name'));
        $secret = (string) ($this->option('secret') ?: $this->secret('Coinbase CDP API Secret PEM (\\n escaped is OK)'));
        $product = strtoupper((string) $this->option('product'));

        if ($product !== CoinbaseExecutionSyncService::ALL_STABLE_QUOTE_PRODUCTS
            && ! preg_match('/^[A-Z0-9]+-(USD|USDC|USDT)$/', $product)) {
            $this->error('Only ALL_COINBASE_STABLE_QUOTE_PRODUCTS or USD/USDC/USDT spot products are supported.');

            return self::FAILURE;
        }

        if (! $this->option('skip-read-check')) {
            (new CoinbaseClient($key, $secret, config('services.coinbase.base_url')))
                ->accounts();
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => $product,
            ],
            [
                'label' => 'Coinbase '.$this->labelForProduct($product),
                'api_key' => $key,
                'api_secret' => $secret,
                'api_passphrase' => null,
                'sync_start_at' => $this->syncStartAt(),
                'is_active' => true,
            ],
        );

        $this->info('Coinbase connection saved.');

        return self::SUCCESS;
    }

    private function labelForProduct(string $product): string
    {
        return $product === CoinbaseExecutionSyncService::ALL_STABLE_QUOTE_PRODUCTS
            ? '全USD/USDC/USDT建て現物'
            : $product;
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
