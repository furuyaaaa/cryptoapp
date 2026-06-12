<?php

namespace App\Console\Commands;

use App\Models\ExchangeConnection;
use App\Services\Exchanges\CoinbaseExecutionSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCoinbaseExecutions extends Command
{
    protected $signature = 'coinbase:sync-executions {--connection= : 特定の exchange_connections.id のみ同期}';

    protected $description = 'Coinbase Advanced Trade の約定履歴を transactions に取り込む';

    public function handle(CoinbaseExecutionSyncService $sync): int
    {
        $connections = ExchangeConnection::query()
            ->where('is_active', true)
            ->whereHas('exchange', fn ($q) => $q->where('code', 'coinbase'))
            ->when($this->option('connection'), fn ($q, $id) => $q->whereKey($id))
            ->with('exchange')
            ->get();

        if ($connections->isEmpty()) {
            $this->warn('No active Coinbase connections found.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($connections as $connection) {
            try {
                $result = $sync->sync($connection);
                $this->info(sprintf(
                    'Connection %d: fetched=%d imported=%d skipped=%d',
                    $connection->id,
                    $result['fetched'],
                    $result['imported'],
                    $result['skipped'],
                ));
            } catch (Throwable $e) {
                $failed = true;
                $this->error(sprintf('Connection %d failed: %s', $connection->id, $e->getMessage()));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
