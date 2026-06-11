<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\Exchanges\Concerns\SkipsExecutionsBeforeSyncStart;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class KuCoinExecutionSyncService
{
    use SkipsExecutionsBeforeSyncStart;

    public const ALL_USDT_SYMBOLS = 'ALL_KUCOIN_USDT_SYMBOLS';

    private const WINDOW_MILLISECONDS = 604_800_000; // 7 days

    public function __construct(private readonly KuCoinExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?KuCoinClient $client = null): array
    {
        $client ??= new KuCoinClient(
            $connection->api_key,
            $connection->api_secret,
            (string) $connection->api_passphrase,
            config('services.kucoin.base_url', 'https://api.kucoin.com'),
        );

        try {
            $symbols = $this->symbolsFor($connection, $client);
            $fetched = 0;
            $imported = 0;
            $skipped = 0;

            foreach ($symbols as $symbol) {
                foreach ($this->fillBatches($client, $symbol, $connection->sync_start_at) as $fills) {
                    $fetched += count($fills);

                    foreach ($fills as $fill) {
                        if (! is_array($fill) || (! isset($fill['tradeId']) && ! isset($fill['id']))) {
                            $skipped++;

                            continue;
                        }

                        $fill['symbol'] = $symbol;
                        $externalId = $symbol.':'.(string) ($fill['tradeId'] ?? $fill['id']);
                        $exists = Transaction::query()
                            ->where('exchange_id', $connection->exchange_id)
                            ->where('external_source', 'kucoin:fills')
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
    private function symbolsFor(ExchangeConnection $connection, KuCoinClient $client): array
    {
        if ($connection->product_code !== self::ALL_USDT_SYMBOLS) {
            return [$connection->product_code];
        }

        $symbols = collect($client->symbols())
            ->filter(fn ($symbol) => (bool) ($symbol['enableTrading'] ?? false))
            ->filter(fn ($symbol) => ($symbol['quoteCurrency'] ?? null) === 'USDT')
            ->pluck('symbol')
            ->filter(fn ($symbol) => is_string($symbol) && str_ends_with($symbol, '-USDT'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($symbols === []) {
            throw new RuntimeException('No USDT spot symbols returned from KuCoin.');
        }

        return $symbols;
    }

    /**
     * @return iterable<list<array<string, mixed>>>
     */
    private function fillBatches(KuCoinClient $client, string $symbol, ?Carbon $syncStartAt): iterable
    {
        $now = now();
        $cursor = $syncStartAt?->copy() ?? $now->copy()->subDays(7)->startOfDay();

        while ($cursor->lessThanOrEqualTo($now)) {
            $end = $cursor->copy()->addMilliseconds(self::WINDOW_MILLISECONDS - 1);
            if ($end->greaterThan($now)) {
                $end = $now;
            }

            $lastId = null;
            do {
                $batch = $client->fills(
                    symbol: $symbol,
                    startAt: $cursor->getTimestampMs(),
                    endAt: $end->getTimestampMs(),
                    limit: 100,
                    lastId: $lastId,
                );

                yield $batch['items'];

                $lastId = $batch['lastId'];
            } while (count($batch['items']) === 100 && $lastId !== null && $lastId !== '');

            $cursor = $end->copy()->addMillisecond();
        }
    }

    /**
     * @param  array<string, mixed>  $fill
     */
    private function isFillBeforeSyncStart(ExchangeConnection $connection, array $fill): bool
    {
        if ($connection->sync_start_at === null || ! isset($fill['createdAt'])) {
            return false;
        }

        return Carbon::createFromTimestampMs((int) $fill['createdAt'])->lt($connection->sync_start_at);
    }
}
