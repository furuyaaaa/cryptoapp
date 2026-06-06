<?php

namespace App\Console\Commands;

use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\Exchanges\BitFlyerClient;
use App\Services\Exchanges\BitFlyerExecutionSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CreateBitFlyerConnection extends Command
{
    protected $signature = 'bitflyer:connect
        {email : 接続を作成するユーザーのメールアドレス}
        {portfolio : 同期先ポートフォリオID}
        {--product=ALL_SPOT_JPY : bitFlyer product_code}
        {--key= : bitFlyer API Key}
        {--secret= : bitFlyer API Secret}
        {--sync-start-date=today : today, all, or YYYY-MM-DD}
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
        $productCode = (string) $this->option('product');

        if ($productCode !== BitFlyerExecutionSyncService::ALL_SPOT_JPY && ! str_ends_with($productCode, '_JPY')) {
            $this->error('Only ALL_SPOT_JPY or JPY spot product codes are supported.');

            return self::FAILURE;
        }

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
                'product_code' => $productCode,
            ],
            [
                'label' => 'bitFlyer '.$this->labelForProduct($productCode),
                'api_key' => $key,
                'api_secret' => $secret,
                'sync_start_at' => $this->syncStartAt(),
                'is_active' => true,
            ],
        );

        $this->info('bitFlyer connection saved.');

        return self::SUCCESS;
    }

    private function labelForProduct(string $productCode): string
    {
        return $productCode === BitFlyerExecutionSyncService::ALL_SPOT_JPY
            ? '全JPY建てSpot'
            : $productCode;
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
