<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExchangeConnectionRequest;
use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Services\Exchanges\BinanceClient;
use App\Services\Exchanges\BinanceExecutionSyncService;
use App\Services\Exchanges\BitbankClient;
use App\Services\Exchanges\BitbankExecutionSyncService;
use App\Services\Exchanges\BitgetClient;
use App\Services\Exchanges\BitgetExecutionSyncService;
use App\Services\Exchanges\BitFlyerClient;
use App\Services\Exchanges\BitFlyerExecutionSyncService;
use App\Services\Exchanges\CoincheckClient;
use App\Services\Exchanges\CoincheckExecutionSyncService;
use App\Services\Exchanges\GmoCoinClient;
use App\Services\Exchanges\GmoCoinExecutionSyncService;
use App\Services\Exchanges\KuCoinClient;
use App\Services\Exchanges\KuCoinExecutionSyncService;
use App\Services\Exchanges\ZaifClient;
use App\Services\Exchanges\ZaifExecutionSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ExchangeConnectionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('ExchangeConnections/Index', [
            'connections' => ExchangeConnection::query()
                ->where('user_id', $user->id)
                ->with(['exchange:id,name,code', 'portfolio:id,name'])
                ->latest()
                ->get()
                ->map(fn (ExchangeConnection $connection) => [
                    'id' => $connection->id,
                    'label' => $connection->label,
                    'exchange' => $connection->exchange,
                    'portfolio' => $connection->portfolio,
                    'product_code' => $connection->product_code,
                    'sync_start_at' => $connection->sync_start_at?->toDateString(),
                    'is_active' => $connection->is_active,
                    'last_synced_at' => $connection->last_synced_at?->toIso8601String(),
                    'last_error_at' => $connection->last_error_at?->toIso8601String(),
                    'last_error' => $connection->last_error,
                    'created_at' => $connection->created_at?->toIso8601String(),
                ]),
            'portfolios' => $user->portfolios()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(ExchangeConnectionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $exchangeCode = $validated['exchange_code'];

        try {
            $this->assertReadableKey(
                $exchangeCode,
                $validated['api_key'],
                $validated['api_secret'],
                $validated['api_passphrase'] ?? null,
            );
        } catch (Throwable $e) {
            return back()
                ->withInput($request->safe()->except(['api_secret', 'api_passphrase']))
                ->withErrors(['api_key' => $e->getMessage()]);
        }

        $exchange = Exchange::firstOrCreate(
            ['code' => $exchangeCode],
            ['name' => $this->exchangeName($exchangeCode), 'country' => $this->exchangeCountry($exchangeCode)],
        );

        ExchangeConnection::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'exchange_id' => $exchange->id,
                'portfolio_id' => $validated['portfolio_id'],
                'product_code' => $validated['product_code'],
            ],
            [
                'label' => $this->labelForConnection($exchangeCode, $validated['product_code']),
                'api_key' => $validated['api_key'],
                'api_secret' => $validated['api_secret'],
                'api_passphrase' => $validated['api_passphrase'] ?? null,
                'sync_start_at' => $this->syncStartAt($validated),
                'is_active' => true,
                'last_error_at' => null,
                'last_error' => null,
            ],
        );

        return redirect()
            ->route('exchange-connections.index')
            ->with('success', $exchange->name.'連携を保存しました。');
    }

    public function sync(
        Request $request,
        ExchangeConnection $connection,
        BitFlyerExecutionSyncService $bitFlyerSync,
        BitbankExecutionSyncService $bitbankSync,
        CoincheckExecutionSyncService $coincheckSync,
        GmoCoinExecutionSyncService $gmoCoinSync,
        ZaifExecutionSyncService $zaifSync,
        BinanceExecutionSyncService $binanceSync,
        BitgetExecutionSyncService $bitgetSync,
        KuCoinExecutionSyncService $kuCoinSync,
    ): RedirectResponse {
        abort_unless($connection->user_id === $request->user()->id, 403);
        $connection->loadMissing('exchange');

        try {
            $result = match ($connection->exchange->code) {
                'bitflyer' => $bitFlyerSync->sync($connection),
                'bitbank' => $bitbankSync->sync($connection),
                'coincheck' => $coincheckSync->sync($connection),
                'gmo_coin' => $gmoCoinSync->sync($connection),
                'zaif' => $zaifSync->sync($connection),
                'binance' => $binanceSync->sync($connection),
                'bitget' => $bitgetSync->sync($connection),
                'kucoin' => $kuCoinSync->sync($connection),
                default => throw new \RuntimeException('Unsupported exchange: '.$connection->exchange->code),
            };
        } catch (Throwable $e) {
            return back()->with('error', '同期に失敗しました: '.$e->getMessage());
        }

        return back()->with(
            'success',
            "同期しました。取得 {$result['fetched']} 件 / 追加 {$result['imported']} 件 / スキップ {$result['skipped']} 件"
        );
    }

    public function destroy(Request $request, ExchangeConnection $connection): RedirectResponse
    {
        abort_unless($connection->user_id === $request->user()->id, 403);

        $connection->delete();

        return back()->with('success', '取引所連携を削除しました。');
    }

    private function assertReadableKey(string $exchangeCode, string $apiKey, string $apiSecret, ?string $apiPassphrase): void
    {
        match ($exchangeCode) {
            'bitflyer' => $this->assertReadOnlyBitFlyerKey($apiKey, $apiSecret),
            'bitbank' => $this->assertReadableBitbankKey($apiKey, $apiSecret),
            'coincheck' => $this->assertReadableCoincheckKey($apiKey, $apiSecret),
            'gmo_coin' => $this->assertReadableGmoCoinKey($apiKey, $apiSecret),
            'zaif' => $this->assertReadableZaifKey($apiKey, $apiSecret),
            'binance' => $this->assertReadableBinanceKey($apiKey, $apiSecret),
            'bitget' => $this->assertReadableBitgetKey($apiKey, $apiSecret, (string) $apiPassphrase),
            'kucoin' => $this->assertReadableKuCoinKey($apiKey, $apiSecret, (string) $apiPassphrase),
            default => throw new \RuntimeException('Unsupported exchange: '.$exchangeCode),
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncStartAt(array $validated): ?Carbon
    {
        return match ($validated['sync_start_mode']) {
            'all' => null,
            'custom' => Carbon::createFromFormat('Y-m-d', (string) $validated['sync_start_date'])->startOfDay(),
            default => today()->startOfDay(),
        };
    }

    private function assertReadOnlyBitFlyerKey(string $apiKey, string $apiSecret): void
    {
        $permissions = (new BitFlyerClient($apiKey, $apiSecret, config('services.bitflyer.base_url')))
            ->permissions();

        $dangerous = array_filter($permissions, fn ($permission) => str_contains((string) $permission, 'send')
            || str_contains((string) $permission, 'cancel')
            || str_contains((string) $permission, 'withdraw'));

        if ($dangerous !== []) {
            throw new \RuntimeException('読み取り専用のAPIキーを指定してください。危険な権限: '.implode(', ', $dangerous));
        }

        if (! in_array('/v1/me/getexecutions', $permissions, true)) {
            throw new \RuntimeException('/v1/me/getexecutions 権限が必要です。');
        }
    }

    private function assertReadableBitbankKey(string $apiKey, string $apiSecret): void
    {
        (new BitbankClient($apiKey, $apiSecret, config('services.bitbank.base_url')))
            ->assets();
    }

    private function assertReadableCoincheckKey(string $apiKey, string $apiSecret): void
    {
        (new CoincheckClient($apiKey, $apiSecret, config('services.coincheck.base_url')))
            ->balance();
    }

    private function assertReadableGmoCoinKey(string $apiKey, string $apiSecret): void
    {
        (new GmoCoinClient($apiKey, $apiSecret, config('services.gmo_coin.base_url')))
            ->assets();
    }

    private function assertReadableZaifKey(string $apiKey, string $apiSecret): void
    {
        $info = (new ZaifClient($apiKey, $apiSecret, config('services.zaif.base_url')))
            ->info();

        if ((int) data_get($info, 'rights.info', 0) !== 1) {
            throw new \RuntimeException('Zaif APIキーには info 権限が必要です。');
        }
    }

    private function assertReadableBinanceKey(string $apiKey, string $apiSecret): void
    {
        (new BinanceClient($apiKey, $apiSecret, config('services.binance.base_url')))
            ->account();
    }

    private function assertReadableBitgetKey(string $apiKey, string $apiSecret, string $apiPassphrase): void
    {
        (new BitgetClient($apiKey, $apiSecret, $apiPassphrase, config('services.bitget.base_url')))
            ->assets('USDT');
    }

    private function assertReadableKuCoinKey(string $apiKey, string $apiSecret, string $apiPassphrase): void
    {
        (new KuCoinClient($apiKey, $apiSecret, $apiPassphrase, config('services.kucoin.base_url')))
            ->apiKeyInfo();
    }

    private function labelForConnection(string $exchangeCode, string $productCode): string
    {
        return match ($exchangeCode) {
            'bitflyer' => 'bitFlyer '.$this->labelForBitFlyerProduct($productCode),
            'bitbank' => 'bitbank '.$this->labelForBitbankPair($productCode),
            'coincheck' => 'Coincheck '.$this->labelForCoincheckPair($productCode),
            'gmo_coin' => 'GMOコイン '.$this->labelForGmoCoinSymbol($productCode),
            'zaif' => 'Zaif '.$this->labelForZaifPair($productCode),
            'binance' => 'Binance Japan '.$this->labelForBinanceSymbol($productCode),
            'bitget' => 'Bitget '.$this->labelForBitgetSymbol($productCode),
            'kucoin' => 'KuCoin '.$this->labelForKuCoinSymbol($productCode),
            default => $exchangeCode.' '.$productCode,
        };
    }

    private function exchangeName(string $exchangeCode): string
    {
        return match ($exchangeCode) {
            'bitflyer' => 'bitFlyer',
            'bitbank' => 'bitbank',
            'coincheck' => 'Coincheck',
            'gmo_coin' => 'GMOコイン',
            'zaif' => 'Zaif',
            'binance' => 'Binance Japan',
            'bitget' => 'Bitget',
            'kucoin' => 'KuCoin',
            default => $exchangeCode,
        };
    }

    private function exchangeCountry(string $exchangeCode): ?string
    {
        return match ($exchangeCode) {
            'bitflyer', 'bitbank', 'coincheck', 'gmo_coin', 'zaif', 'binance' => 'JP',
            default => null,
        };
    }

    private function labelForBitFlyerProduct(string $productCode): string
    {
        return $productCode === BitFlyerExecutionSyncService::ALL_SPOT_JPY
            ? '全JPY建てSpot'
            : $productCode;
    }

    private function labelForBitbankPair(string $pair): string
    {
        return $pair === BitbankExecutionSyncService::ALL_JPY_PAIRS
            ? '全JPY建て現物'
            : $pair;
    }

    private function labelForCoincheckPair(string $pair): string
    {
        return $pair === CoincheckExecutionSyncService::ALL_JPY_PAIRS
            ? '全JPY建て取引所ペア'
            : $pair;
    }

    private function labelForGmoCoinSymbol(string $symbol): string
    {
        return $symbol === GmoCoinExecutionSyncService::ALL_SPOT_SYMBOLS
            ? '全現物銘柄'
            : $symbol;
    }

    private function labelForZaifPair(string $pair): string
    {
        return $pair === ZaifExecutionSyncService::ALL_JPY_PAIRS
            ? '全JPY建て現物'
            : $pair;
    }

    private function labelForBinanceSymbol(string $symbol): string
    {
        return $symbol === BinanceExecutionSyncService::ALL_JPY_SYMBOLS
            ? '全JPY建て現物'
            : $symbol;
    }

    private function labelForBitgetSymbol(string $symbol): string
    {
        return $symbol === BitgetExecutionSyncService::ALL_USDT_SYMBOLS
            ? '全USDT建て現物'
            : $symbol;
    }

    private function labelForKuCoinSymbol(string $symbol): string
    {
        return $symbol === KuCoinExecutionSyncService::ALL_USDT_SYMBOLS
            ? '全USDT建て現物'
            : $symbol;
    }
}
