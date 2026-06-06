<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use Throwable;

class BitFlyerExecutionSyncService
{
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
            $executions = $client->executions($connection->product_code);
            $imported = 0;
            $skipped = 0;

            foreach ($executions as $execution) {
                $externalId = (string) ($execution['id'] ?? '');
                if ($externalId === '') {
                    $skipped++;

                    continue;
                }

                $exists = Transaction::query()
                    ->where('exchange_id', $connection->exchange_id)
                    ->where('external_source', 'bitflyer:getexecutions')
                    ->where('external_id', $externalId)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                Transaction::create($this->mapper->map($connection, $execution));
                $imported++;
            }

            $connection->forceFill([
                'last_synced_at' => now(),
                'last_error_at' => null,
                'last_error' => null,
            ])->save();

            return [
                'fetched' => count($executions),
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
}
