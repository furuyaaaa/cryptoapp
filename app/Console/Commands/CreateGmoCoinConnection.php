<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\GmoCoinClient;
use App\Services\Exchanges\GmoCoinExecutionSyncService;
use Illuminate\Console\Command;

class CreateGmoCoinConnection extends Command
{
    protected $signature = 'gmo-coin:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--symbol=ALL_SPOT_SYMBOLS : GMO Coin spot symbol}
        {--key= : GMO Coin API Key}
        {--secret= : GMO Coin API Secret}
        {--skip-read-check : 読み取りAPIを呼ばずに保存する}';

    protected $description = 'GMOコイン の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'gmo_coin'],
            ['name' => 'GMOコイン', 'country' => 'JP'],
        );

        $key = (string) ($this->option('key') ?: $this->secret('GMO Coin API Key'));
        $secret = (string) ($this->option('secret') ?: $this->secret('GMO Coin API Secret'));
        $symbol = (string) $this->option('symbol');

        if ($symbol !== GmoCoinExecutionSyncService::ALL_SPOT_SYMBOLS
            && ! in_array($symbol, GmoCoinExecutionSyncService::SUPPORTED_SPOT_SYMBOLS, true)) {
            $this->error('Only ALL_SPOT_SYMBOLS or supported spot symbols are supported.');

            return self::FAILURE;
        }

        if (! $this->option('skip-read-check')) {
            (new GmoCoinClient($key, $secret, config('services.gmo_coin.base_url')))
                ->assets();
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => $symbol,
            ],
            [
                'label' => 'GMOコイン '.$this->labelForSymbol($symbol),
                'api_key' => $key,
                'api_secret' => $secret,
                'is_active' => true,
            ],
        );

        $this->info('GMO Coin connection saved.');

        return self::SUCCESS;
    }

    private function labelForSymbol(string $symbol): string
    {
        return $symbol === GmoCoinExecutionSyncService::ALL_SPOT_SYMBOLS
            ? '全現物銘柄'
            : $symbol;
    }
}
