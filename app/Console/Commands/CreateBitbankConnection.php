<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\BitbankClient;
use App\Services\Exchanges\BitbankExecutionSyncService;
use Illuminate\Console\Command;

class CreateBitbankConnection extends Command
{
    protected $signature = 'bitbank:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--pair=ALL_JPY_PAIRS : bitbank pair}
        {--key= : bitbank API Key}
        {--secret= : bitbank API Secret}
        {--skip-read-check : 読み取りAPIを呼ばずに保存する}';

    protected $description = 'bitbank の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'bitbank'],
            ['name' => 'bitbank', 'country' => 'JP'],
        );

        $key = (string) ($this->option('key') ?: $this->secret('bitbank API Key'));
        $secret = (string) ($this->option('secret') ?: $this->secret('bitbank API Secret'));
        $pair = (string) $this->option('pair');

        if ($pair !== BitbankExecutionSyncService::ALL_JPY_PAIRS && ! str_ends_with($pair, '_jpy')) {
            $this->error('Only ALL_JPY_PAIRS or JPY spot pairs are supported.');

            return self::FAILURE;
        }

        if (! $this->option('skip-read-check')) {
            (new BitbankClient($key, $secret, config('services.bitbank.base_url')))
                ->assets();
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => $pair,
            ],
            [
                'label' => 'bitbank '.$this->labelForPair($pair),
                'api_key' => $key,
                'api_secret' => $secret,
                'is_active' => true,
            ],
        );

        $this->info('bitbank connection saved.');

        return self::SUCCESS;
    }

    private function labelForPair(string $pair): string
    {
        return $pair === BitbankExecutionSyncService::ALL_JPY_PAIRS
            ? '全JPY建て現物'
            : $pair;
    }
}
