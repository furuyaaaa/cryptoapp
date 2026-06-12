<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\Exchanges\Concerns\SkipsExecutionsBeforeSyncStart;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class CoinbaseExecutionSyncService
{
    use SkipsExecutionsBeforeSyncStart;

    public const ALL_STABLE_QUOTE_PRODUCTS = 'ALL_COINBASE_STABLE_QUOTE_PRODUCTS';

    public function __construct(private readonly CoinbaseExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?CoinbaseClient $client = null): array
    {
        $client ??= new CoinbaseClient(
            $connection->api_key,
            $connection->api_secret,
            config('services.coinbase.base_url', 'https://api.coinbase.com'),
        );

        try {
            $products = $this->productsFor($connection, $client);
            $fetched = 0;
            $imported = 0;
            $skipped = 0;

            foreach ($products as $productId) {
                $cursor = null;

                do {
                    $batch = $client->fills(
                        productId: $productId,
                        startSequenceTimestamp: $this->startTimestamp($connection),
                        limit: 100,
                        cursor: $cursor,
                    );

                    $fills = $batch['items'];
                    $fetched += count($fills);

                    foreach ($fills as $fill) {
                        if (! is_array($fill) || (! isset($fill['entry_id']) && ! isset($fill['trade_id']))) {
                            $skipped++;

                            continue;
                        }

                        $fill['product_id'] = (string) ($fill['product_id'] ?? $productId);
                        $externalId = strtoupper($fill['product_id']).':'.(string) ($fill['entry_id'] ?? $fill['trade_id']);
                        $exists = Transaction::query()
                            ->where('exchange_id', $connection->exchange_id)
                            ->where('external_source', 'coinbase:fills')
                            ->where('external_id', $externalId)
                            ->exists();

                        if ($exists) {
                            $skipped++;

                            continue;
                        }

                        if ($this->isFillBeforeSyncStart($connection, $fill)) {
                            $skipped++;

                            continue;
                        }

                        $mapped = $this->mapper->map($connection, $fill);
                        if ($this->isBeforeSyncStart($connection, $mapped)) {
                            $skipped++;

                            continue;
                        }

                        Transaction::create($mapped);
                        $imported++;
                    }

                    $cursor = $batch['cursor'];
                } while (count($fills) === 100 && $cursor !== null && $cursor !== '');
            }

            $connection->forceFill([
                'last_synced_at' => now(),
                'last_error_at' => null,
                'last_error' => null,
            ])->save();

            return [
                'fetched' => $fetched,
                'imported' => $imported,
                'skipped' => $skipped,
            ];
        } catch (Throwable $e) {
            $connection->forceFill([
                'last_error_at' => now(),
                'last_error' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    /**
     * @return list<string>
     */
    private function productsFor(ExchangeConnection $connection, CoinbaseClient $client): array
    {
        if ($connection->product_code !== self::ALL_STABLE_QUOTE_PRODUCTS) {
            return [$connection->product_code];
        }

        $products = collect($client->products())
            ->filter(fn ($product) => ! (bool) ($product['is_disabled'] ?? false))
            ->filter(fn ($product) => ! (bool) ($product['trading_disabled'] ?? false))
            ->filter(fn ($product) => in_array(strtoupper((string) ($product['quote_currency_id'] ?? $product['quote_display_symbol'] ?? '')), ['USD', 'USDC', 'USDT'], true))
            ->pluck('product_id')
            ->filter(fn ($productId) => is_string($productId) && preg_match('/^[A-Z0-9]+-(USD|USDC|USDT)$/', $productId))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($products === []) {
            throw new RuntimeException('No USD/USDC/USDT spot products returned from Coinbase.');
        }

        return $products;
    }

    private function startTimestamp(ExchangeConnection $connection): ?string
    {
        return $connection->sync_start_at?->toRfc3339String();
    }

    /**
     * @param  array<string, mixed>  $fill
     */
    private function isFillBeforeSyncStart(ExchangeConnection $connection, array $fill): bool
    {
        if ($connection->sync_start_at === null || ! isset($fill['trade_time'])) {
            return false;
        }

        return Carbon::parse((string) $fill['trade_time'])->lt($connection->sync_start_at);
    }
}
