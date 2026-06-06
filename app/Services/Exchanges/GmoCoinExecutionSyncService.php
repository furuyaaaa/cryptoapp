<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use Throwable;

class GmoCoinExecutionSyncService
{
    public const ALL_SPOT_SYMBOLS = 'ALL_SPOT_SYMBOLS';

    public const SUPPORTED_SPOT_SYMBOLS = [
        'BTC',
        'ETH',
        'BCH',
        'LTC',
        'XRP',
        'XLM',
        'XTZ',
        'DOT',
        'ATOM',
        'FCR',
        'ADA',
        'LINK',
        'DOGE',
        'SOL',
        'ASTR',
        'NAC',
        'SUI',
        'WILD',
    ];

    public function __construct(private readonly GmoCoinExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?GmoCoinClient $client = null): array
    {
        $client ??= new GmoCoinClient(
            $connection->api_key,
            $connection->api_secret,
            config('services.gmo_coin.base_url', 'https://api.coin.z.com/private'),
        );

        try {
            $symbols = $this->symbolsFor($connection);
            $fetched = 0;
            $imported = 0;
            $skipped = 0;

            foreach ($symbols as $symbol) {
                $executions = $client->latestExecutions($symbol);
                $fetched += count($executions);

                foreach ($executions as $execution) {
                    $executionId = (string) ($execution['executionId'] ?? '');
                    if ($executionId === '') {
                        $skipped++;

                        continue;
                    }

                    $externalId = $symbol.':'.$executionId;
                    $exists = Transaction::query()
                        ->where('exchange_id', $connection->exchange_id)
                        ->where('external_source', 'gmo_coin:latest_executions')
                        ->where('external_id', $externalId)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    Transaction::create($this->mapper->map($connection, $execution));
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
    private function symbolsFor(ExchangeConnection $connection): array
    {
        if ($connection->product_code === self::ALL_SPOT_SYMBOLS) {
            return self::SUPPORTED_SPOT_SYMBOLS;
        }

        return [$connection->product_code];
    }
}
