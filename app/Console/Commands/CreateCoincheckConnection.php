<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\CoincheckClient;
use App\Services\Exchanges\CoincheckExecutionSyncService;
use Illuminate\Console\Command;

class CreateCoincheckConnection extends Command
{
    protected $signature = 'coincheck:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--pair=ALL_JPY_PAIRS : Coincheck pair}
        {--key= : Coincheck API Key}
        {--secret= : Coincheck API Secret}
        {--skip-read-check : 読み取りAPIを呼ばずに保存する}';

    protected $description = 'Coincheck の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'coincheck'],
            ['name' => 'Coincheck', 'country' => 'JP'],
        );

        $key = (string) ($this->option('key') ?: $this->secret('Coincheck API Key'));
        $secret = (string) ($this->option('secret') ?: $this->secret('Coincheck API Secret'));
        $pair = (string) $this->option('pair');

        if ($pair !== CoincheckExecutionSyncService::ALL_JPY_PAIRS
            && ! in_array($pair, CoincheckExecutionSyncService::SUPPORTED_JPY_PAIRS, true)) {
            $this->error('Only ALL_JPY_PAIRS or supported JPY pairs are supported.');

            return self::FAILURE;
        }

        if (! $this->option('skip-read-check')) {
            (new CoincheckClient($key, $secret, config('services.coincheck.base_url')))
                ->balance();
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => $pair,
            ],
            [
                'label' => 'Coincheck '.$this->labelForPair($pair),
                'api_key' => $key,
                'api_secret' => $secret,
                'is_active' => true,
            ],
        );

        $this->info('Coincheck connection saved.');

        return self::SUCCESS;
    }

    private function labelForPair(string $pair): string
    {
        return $pair === CoincheckExecutionSyncService::ALL_JPY_PAIRS
            ? '全JPY建て取引所ペア'
            : $pair;
    }
}
