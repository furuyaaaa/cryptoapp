<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\Exchanges\Concerns\SkipsExecutionsBeforeSyncStart;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class BitgetExecutionSyncService
{
    use SkipsExecutionsBeforeSyncStart;

    public const ALL_USDT_SYMBOLS = 'ALL_USDT_SYMBOLS';

    private const WINDOW_MILLISECONDS = 7_776_000_000; // 90 days

    public function __construct(private readonly BitgetExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?BitgetClient $client = null): array
    {
        $client ??= new BitgetClient(
            $connection->api_key,
            $connection->api_secret,
            (string) $connection->api_passphrase,
            config('services.bitget.base_url', 'https://api.bitget.com'),
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
                        if (! is_array($fill) || ! isset($fill['tradeId'])) {
                            $skipped++;

                            continue;
                        }

                        $fill['symbol'] = $symbol;
                        $externalId = $symbol.':'.(string) $fill['tradeId'];
                        $exists = Transaction::query()
                            ->where('exchange_id', $connection->exchange_id)
                            ->where('external_source', 'bitget:fills')
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
    private function symbolsFor(ExchangeConnection $connection, BitgetClient $client): array
    {
        if ($connection->product_code !== self::ALL_USDT_SYMBOLS) {
            return [$connection->product_code];
        }

        $symbols = collect($client->symbols())
            ->filter(fn ($symbol) => ($symbol['status'] ?? null) === 'online')
            ->filter(fn ($symbol) => ($symbol['quoteCoin'] ?? null) === 'USDT')
            ->pluck('symbol')
            ->filter(fn ($symbol) => is_string($symbol) && str_ends_with($symbol, 'USDT'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($symbols === []) {
            throw new RuntimeException('No USDT spot symbols returned from Bitget.');
        }

        return $symbols;
    }

    /**
     * @return iterable<list<array<string, mixed>>>
     */
    private function fillBatches(BitgetClient $client, string $symbol, ?Carbon $syncStartAt): iterable
    {
        $now = now();
        $oldestSupported = $now->copy()->subDays(90)->startOfDay();
        $cursor = $syncStartAt?->copy() ?? $oldestSupported;
        if ($cursor->lessThan($oldestSupported)) {
            $cursor = $oldestSupported;
        }

        while ($cursor->lessThanOrEqualTo($now)) {
            $end = $cursor->copy()->addMilliseconds(self::WINDOW_MILLISECONDS - 1);
            if ($end->greaterThan($now)) {
                $end = $now;
            }

            $idLessThan = null;
            do {
                $fills = $client->fills(
                    symbol: $symbol,
                    startTime: $cursor->getTimestampMs(),
                    endTime: $end->getTimestampMs(),
                    limit: 100,
                    idLessThan: $idLessThan,
                );

                yield $fills;

                $last = end($fills);
                $idLessThan = is_array($last) ? (string) ($last['tradeId'] ?? '') : null;
            } while (count($fills) === 100 && $idLessThan !== '');

            $cursor = $end->copy()->addMillisecond();
        }
    }

    /**
     * @param  array<string, mixed>  $fill
     */
    private function isFillBeforeSyncStart(ExchangeConnection $connection, array $fill): bool
    {
        if ($connection->sync_start_at === null || ! isset($fill['cTime'])) {
            return false;
        }

        return Carbon::createFromTimestampMs((int) $fill['cTime'])->lt($connection->sync_start_at);
    }
}
