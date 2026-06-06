<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\Exchanges\Concerns\SkipsExecutionsBeforeSyncStart;
use Throwable;

class BitFlyerExecutionSyncService
{
    use SkipsExecutionsBeforeSyncStart;

    public const ALL_SPOT_JPY = 'ALL_SPOT_JPY';

    public function __construct(private readonly BitFlyerExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?BitFlyerClient $client = null): array
    {
        $client ??= new BitFlyerClient(
            $connection->api_key,
            $connection->api_secret,
            config('services.bitflyer.base_url', 'https://api.bitflyer.com'),
        );

        try {
            $productCodes = $this->productCodesFor($connection, $client);
            $fetched = 0;
            $imported = 0;
            $skipped = 0;

            foreach ($productCodes as $productCode) {
                $executions = $client->executions($productCode);
                $fetched += count($executions);

                foreach ($executions as $execution) {
                    $executionId = (string) ($execution['id'] ?? '');
                    if ($executionId === '') {
                        $skipped++;

                        continue;
                    }

                    $externalId = $productCode.':'.$executionId;
                    $exists = Transaction::query()
                        ->where('exchange_id', $connection->exchange_id)
                        ->where('external_source', 'bitflyer:getexecutions')
                        ->where('external_id', $externalId)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $mapped = $this->mapper->map($connection, $execution, $productCode);
                    if ($this->isBeforeSyncStart($connection, $mapped)) {
                        $skipped++;

                        continue;
                    }

                    Transaction::create($mapped);
                    $imported++;
                }
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
    private function productCodesFor(ExchangeConnection $connection, BitFlyerClient $client): array
    {
        if ($connection->product_code !== self::ALL_SPOT_JPY) {
            return [$connection->product_code];
        }

        $codes = collect($client->markets())
            ->filter(fn ($market) => ($market['market_type'] ?? null) === 'Spot')
            ->pluck('product_code')
            ->filter(fn ($code) => is_string($code) && str_ends_with($code, '_JPY'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($codes === []) {
            throw new \RuntimeException('No JPY spot markets returned from bitFlyer.');
        }

        return $codes;
    }
}
