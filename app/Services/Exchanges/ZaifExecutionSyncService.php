<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\Exchanges\Concerns\SkipsExecutionsBeforeSyncStart;
use RuntimeException;
use Throwable;

class ZaifExecutionSyncService
{
    use SkipsExecutionsBeforeSyncStart;

    public const ALL_JPY_PAIRS = 'ALL_JPY_PAIRS';

    public function __construct(private readonly ZaifExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?ZaifClient $client = null): array
    {
        $client ??= new ZaifClient(
            $connection->api_key,
            $connection->api_secret,
            config('services.zaif.base_url', 'https://api.zaif.jp'),
        );

        try {
            $pairs = $this->pairsFor($connection, $client);
            $fetched = 0;
            $imported = 0;
            $skipped = 0;

            foreach ($pairs as $pair) {
                $since = $connection->sync_start_at?->timestamp;
                $trades = $client->tradeHistory($pair, $since);
                $fetched += count($trades);

                foreach ($trades as $tradeId => $trade) {
                    $tradeId = (string) $tradeId;
                    if ($tradeId === '' || ! is_array($trade)) {
                        $skipped++;

                        continue;
                    }

                    $externalId = $pair.':'.$tradeId;
                    $exists = Transaction::query()
                        ->where('exchange_id', $connection->exchange_id)
                        ->where('external_source', 'zaif:trade_history')
                        ->where('external_id', $externalId)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    $mapped = $this->mapper->map($connection, $trade, $tradeId);
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
    private function pairsFor(ExchangeConnection $connection, ZaifClient $client): array
    {
        if ($connection->product_code !== self::ALL_JPY_PAIRS) {
            return [$connection->product_code];
        }

        $pairs = collect($client->currencyPairs())
            ->filter(fn ($pair) => ($pair['is_token'] ?? false) === false)
            ->pluck('currency_pair')
            ->filter(fn ($pair) => is_string($pair) && str_ends_with($pair, '_jpy'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($pairs === []) {
            throw new RuntimeException('No JPY spot pairs returned from Zaif.');
        }

        return $pairs;
    }
}
