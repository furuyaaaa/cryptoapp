<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\BitFlyerClient;
use Illuminate\Console\Command;

class CreateBitFlyerConnection extends Command
{
    protected $signature = 'bitflyer:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--product=BTC_JPY : bitFlyer product_code}
        {--key= : bitFlyer API Key}
        {--secret= : bitFlyer API Secret}
        {--skip-permission-check : 権限確認APIを呼ばずに保存する}';

    protected $description = 'bitFlyer の読み取り用APIキーを暗号化保存する';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->firstOrFail();
        $portfolio = Portfolio::where('user_id', $user->id)
            ->whereKey($this->argument('portfolio'))
            ->firstOrFail();
        $exchange = Exchange::firstOrCreate(
            ['code' => 'bitflyer'],
            ['name' => 'bitFlyer', 'country' => 'JP'],
        );

        $key = (string) ($this->option('key') ?: $this->secret('bitFlyer API Key'));
        $secret = (string) ($this->option('secret') ?: $this->secret('bitFlyer API Secret'));

        if (! $this->option('skip-permission-check')) {
            $permissions = (new BitFlyerClient($key, $secret, config('services.bitflyer.base_url')))
                ->permissions();

            $dangerous = array_filter($permissions, fn ($permission) => str_contains((string) $permission, 'send')
                || str_contains((string) $permission, 'cancel')
                || str_contains((string) $permission, 'withdraw'));

            if ($dangerous !== []) {
                $this->error('Read-only API key required. Dangerous permissions: '.implode(', ', $dangerous));

                return self::FAILURE;
            }

            if (! in_array('/v1/me/getexecutions', $permissions, true)) {
                $this->error('API key must allow /v1/me/getexecutions.');

                return self::FAILURE;
            }
        }

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $user->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $portfolio->id,
                'product_code' => (string) $this->option('product'),
            ],
            [
                'label' => 'bitFlyer '.(string) $this->option('product'),
                'api_key' => $key,
                'api_secret' => $secret,
                'is_active' => true,
            ],
        );

        $this->info('bitFlyer connection saved.');

        return self::SUCCESS;
    }
}
