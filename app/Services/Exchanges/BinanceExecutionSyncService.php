<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\Exchanges\Concerns\SkipsExecutionsBeforeSyncStart;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class BinanceExecutionSyncService
{
    use SkipsExecutionsBeforeSyncStart;

    public const ALL_JPY_SYMBOLS = 'ALL_JPY_SYMBOLS';

    private const WINDOW_MILLISECONDS = 86_399_999;

    public function __construct(private readonly BinanceExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?BinanceClient $client = null): array
    {
        $client ??= new BinanceClient(
            $connection->api_key,
            $connection->api_secret,
            config('services.binance.base_url', 'https://api.binance.com'),
        );

        try {
            $symbols = $this->symbolsFor($connection, $client);
            $fetched = 0;
            $imported = 0;
            $skipped = 0;

            foreach ($symbols as $symbol) {
                foreach ($this->tradeBatches($client, $symbol, $connection->sync_start_at) as $trades) {
                    $fetched += count($trades);

                    foreach ($trades as $trade) {
                        if (! is_array($trade) || ! isset($trade['id'])) {
                            $skipped++;

                            continue;
                        }

                        $trade['symbol'] = $symbol;
                        $externalId = $symbol.':'.(string) $trade['id'];
                        $exists = Transaction::query()
                            ->where('exchange_id', $connection->exchange_id)
                            ->where('external_source', 'binance:my_trades')
                            ->where('external_id', $externalId)
                            ->exists();

                        if ($exists) {
                            $skipped++;

                            continue;
                        }

                        $mapped = $this->mapper->map($connection, $trade);
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
    private function symbolsFor(ExchangeConnection $connection, BinanceClient $client): array
    {
        if ($connection->product_code !== self::ALL_JPY_SYMBOLS) {
            return [$connection->product_code];
        }

        $symbols = collect($client->exchangeInfo()['symbols'] ?? [])
            ->filter(fn ($symbol) => ($symbol['status'] ?? null) === 'TRADING')
            ->filter(fn ($symbol) => ($symbol['quoteAsset'] ?? null) === 'JPY')
            ->filter(fn ($symbol) => ($symbol['isSpotTradingAllowed'] ?? false) === true)
            ->pluck('symbol')
            ->filter(fn ($symbol) => is_string($symbol) && str_ends_with($symbol, 'JPY'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($symbols === []) {
            throw new RuntimeException('No JPY spot symbols returned from Binance.');
        }

        return $symbols;
    }

    /**
     * @return iterable<list<array<string, mixed>>>
     */
    private function tradeBatches(BinanceClient $client, string $symbol, ?Carbon $syncStartAt): iterable
    {
        if ($syncStartAt === null) {
            yield $client->myTrades($symbol);

            return;
        }

        $cursor = $syncStartAt->copy();
        $now = now();

        while ($cursor->lessThanOrEqualTo($now)) {
            $end = $cursor->copy()->addMilliseconds(self::WINDOW_MILLISECONDS);
            if ($end->greaterThan($now)) {
                $end = $now;
            }

            yield $client->myTrades(
                $symbol,
                $cursor->getTimestampMs(),
                $end->getTimestampMs(),
            );

            $cursor = $end->copy()->addMillisecond();
        }
    }
}
