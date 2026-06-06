<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\ZaifClient;
use App\Services\Exchanges\ZaifExecutionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateZaifConnection extends Command
{
    protected $signature = 'zaif:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--pair=ALL_JPY_PAIRS : Zaif currency_pair}
        {--key= : Zaif API Key}
        {--secret= : Zaif API Secret}
        {--sync-start-date=today : today, all, or YYYY-MM-DD}
        {--skip-read-check : 読み取りAPIを呼ばずに保存する}';

    protected $description = 'Zaif の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'zaif'],
            ['name' => 'Zaif', 'country' => 'JP'],
        );

        $key = (string) ($this->option('key') ?: $this->secret('Zaif API Key'));
        $secret = (string) ($this->option('secret') ?: $this->secret('Zaif API Secret'));
        $pair = (string) $this->option('pair');

        if ($pair !== ZaifExecutionSyncService::ALL_JPY_PAIRS && ! str_ends_with($pair, '_jpy')) {
            $this->error('Only ALL_JPY_PAIRS or JPY pairs are supported.');

            return self::FAILURE;
        }

        if (! $this->option('skip-read-check')) {
            $info = (new ZaifClient($key, $secret, config('services.zaif.base_url')))
                ->info();

            if ((int) data_get($info, 'rights.info', 0) !== 1) {
                $this->error('Zaif API key must allow info permission.');

                return self::FAILURE;
            }
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => $pair,
            ],
            [
                'label' => 'Zaif '.$this->labelForPair($pair),
                'api_key' => $key,
                'api_secret' => $secret,
                'sync_start_at' => $this->syncStartAt(),
                'is_active' => true,
            ],
        );

        $this->info('Zaif connection saved.');

        return self::SUCCESS;
    }

    private function labelForPair(string $pair): string
    {
        return $pair === ZaifExecutionSyncService::ALL_JPY_PAIRS
            ? '全JPY建て現物'
            : $pair;
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
