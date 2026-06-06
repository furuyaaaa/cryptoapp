<?php

namespace App\Console\Commands;

use App\Models\ExchangeConnection;
use App\Services\Exchanges\BitbankExecutionSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncBitbankExecutions extends Command
{
    protected $signature = 'bitbank:sync-executions {--connection= : 特定の exchange_connections.id のみ同期}';

    protected $description = 'bitbank の約定履歴を transactions に取り込む';

    public function handle(BitbankExecutionSyncService $sync): int
    {
        $connections = ExchangeConnection::query()
            ->where('is_active', true)
            ->whereHas('exchange', fn ($q) => $q->where('code', 'bitbank'))
            ->when($this->option('connection'), fn ($q, $id) => $q->whereKey($id))
            ->with('exchange')
            ->get();

        if ($connections->isEmpty()) {
            $this->warn('No active bitbank connections found.');

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
