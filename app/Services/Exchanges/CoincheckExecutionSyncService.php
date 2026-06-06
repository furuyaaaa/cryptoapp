<?php

namespace App\Services\Exchanges;

use App\Models\ExchangeConnection;
use App\Models\Transaction;
use App\Services\Exchanges\Concerns\SkipsExecutionsBeforeSyncStart;
use Throwable;

class CoincheckExecutionSyncService
{
    use SkipsExecutionsBeforeSyncStart;

    public const ALL_JPY_PAIRS = 'ALL_JPY_PAIRS';

    public const SUPPORTED_JPY_PAIRS = [
        'btc_jpy',
        'eth_jpy',
        'etc_jpy',
        'lsk_jpy',
        'xrp_jpy',
        'xem_jpy',
        'bch_jpy',
        'mona_jpy',
        'iost_jpy',
        'chz_jpy',
        'imx_jpy',
        'shib_jpy',
        'avax_jpy',
        'fnct_jpy',
        'dai_jpy',
        'wbtc_jpy',
        'bril_jpy',
        'bc_jpy',
        'doge_jpy',
        'pepe_jpy',
        'mask_jpy',
        'mana_jpy',
        'grt_jpy',
        'trx_jpy',
        'sol_jpy',
        'fpl_jpy',
        'sui_jpy',
    ];

    public function __construct(private readonly CoincheckExecutionMapper $mapper) {}

    /**
     * @return array{fetched: int, imported: int, skipped: int}
     */
    public function sync(ExchangeConnection $connection, ?CoincheckClient $client = null): array
    {
        $client ??= new CoincheckClient(
            $connection->api_key,
            $connection->api_secret,
            config('services.coincheck.base_url', 'https://coincheck.com'),
        );

        try {
            $transactions = $client->transactions();
            $fetched = count($transactions);
            $imported = 0;
            $skipped = 0;

            foreach ($transactions as $transaction) {
                $pair = (string) ($transaction['pair'] ?? '');
                $transactionId = (string) ($transaction['id'] ?? '');

                if ($transactionId === '' || ! $this->supportsPair($connection, $pair)) {
                    $skipped++;

                    continue;
                }

                $externalId = $pair.':'.$transactionId;
                $exists = Transaction::query()
                    ->where('exchange_id', $connection->exchange_id)
                    ->where('external_source', 'coincheck:transactions')
                    ->where('external_id', $externalId)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $mapped = $this->mapper->map($connection, $transaction);
                if ($this->isBeforeSyncStart($connection, $mapped)) {
                    $skipped++;

                    continue;
                }

                Transaction::create($mapped);
                $imported++;
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

    private function supportsPair(ExchangeConnection $connection, string $pair): bool
    {
        if ($connection->product_code === self::ALL_JPY_PAIRS) {
            return in_array($pair, self::SUPPORTED_JPY_PAIRS, true);
        }

        return $pair === $connection->product_code;
    }
}
