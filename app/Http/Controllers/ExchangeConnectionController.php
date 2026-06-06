<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExchangeConnectionRequest;
use App\Models\Exchange;
use App\Models\ExchangeConnection;
use App\Services\Exchanges\BitbankClient;
use App\Services\Exchanges\BitbankExecutionSyncService;
use App\Services\Exchanges\BitFlyerClient;
use App\Services\Exchanges\BitFlyerExecutionSyncService;
use App\Services\Exchanges\CoincheckClient;
use App\Services\Exchanges\CoincheckExecutionSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $this->assertReadableKey($exchangeCode, $validated['api_key'], $validated['api_secret']);
        } catch (Throwable $e) {
            return back()
                ->withInput($request->safe()->except(['api_secret']))
                ->withErrors(['api_key' => $e->getMessage()]);
        }

        $exchange = Exchange::firstOrCreate(
            ['code' => $exchangeCode],
            ['name' => $this->exchangeName($exchangeCode), 'country' => 'JP'],
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
    ): RedirectResponse {
        abort_unless($connection->user_id === $request->user()->id, 403);
        $connection->loadMissing('exchange');

        try {
            $result = match ($connection->exchange->code) {
                'bitflyer' => $bitFlyerSync->sync($connection),
                'bitbank' => $bitbankSync->sync($connection),
                'coincheck' => $coincheckSync->sync($connection),
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

    private function assertReadableKey(string $exchangeCode, string $apiKey, string $apiSecret): void
    {
        match ($exchangeCode) {
            'bitflyer' => $this->assertReadOnlyBitFlyerKey($apiKey, $apiSecret),
            'bitbank' => $this->assertReadableBitbankKey($apiKey, $apiSecret),
            'coincheck' => $this->assertReadableCoincheckKey($apiKey, $apiSecret),
            default => throw new \RuntimeException('Unsupported exchange: '.$exchangeCode),
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

    private function labelForConnection(string $exchangeCode, string $productCode): string
    {
        return match ($exchangeCode) {
            'bitflyer' => 'bitFlyer '.$this->labelForBitFlyerProduct($productCode),
            'bitbank' => 'bitbank '.$this->labelForBitbankPair($productCode),
            'coincheck' => 'Coincheck '.$this->labelForCoincheckPair($productCode),
            default => $exchangeCode.' '.$productCode,
        };
    }

    private function exchangeName(string $exchangeCode): string
    {
        return match ($exchangeCode) {
            'bitflyer' => 'bitFlyer',
            'bitbank' => 'bitbank',
            'coincheck' => 'Coincheck',
            default => $exchangeCode,
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
}
