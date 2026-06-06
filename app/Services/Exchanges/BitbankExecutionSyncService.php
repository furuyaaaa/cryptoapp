<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use RuntimeException;
use Throwable;

class BitbankExecutionSyncService
{
    public const ALL_JPY_PAIRS = 'ALL_JPY_PAIRS';

    public function __construct(private readonly BitbankExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?BitbankClient $client = null): array
    {
        $client ??= new BitbankClient(
            $connection->api_key,
            $connection->api_secret,
            config('services.bitbank.base_url', 'https://api.bitbank.cc'),
        );

        try {
            $pairs = $this->pairsFor($connection, $client);
            $fetched = 0;
            $imported = 0;
            $skipped = 0;

            foreach ($pairs as $pair) {
                $trades = $client->tradeHistory($pair);
                $fetched += count($trades);

                foreach ($trades as $trade) {
                    $tradeId = (string) ($trade['trade_id'] ?? '');
                    if ($tradeId === '') {
                        $skipped++;

                        continue;
                    }

                    $externalId = $pair.':'.$tradeId;
                    $exists = Transaction::query()
                        ->where('exchange_id', $connection->exchange_id)
                        ->where('external_source', 'bitbank:trade_history')
                        ->where('external_id', $externalId)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    Transaction::create($this->mapper->map($connection, $trade, $pair));
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
    private function pairsFor(ExchangeConnection $connection, BitbankClient $client): array
    {
        if ($connection->product_code !== self::ALL_JPY_PAIRS) {
            return [$connection->product_code];
        }

        $pairs = collect($client->pairs())
            ->filter(fn ($pair) => ($pair['quote_asset'] ?? null) === 'jpy')
            ->filter(fn ($pair) => ($pair['is_enabled'] ?? false) === true)
            ->filter(fn ($pair) => ($pair['stop_order'] ?? false) === false)
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && str_ends_with($name, '_jpy'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($pairs === []) {
            throw new RuntimeException('No JPY spot pairs returned from bitbank.');
        }

        return $pairs;
    }
}
