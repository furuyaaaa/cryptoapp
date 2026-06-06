<?php

namespace App\Services\Exchanges\Concerns;

use App\Models\ExchangeConnection;
use Illuminate\Support\Carbon;

trait SkipsExecutionsBeforeSyncStart
{
    /**
     * @param  array<string, mixed>  $mapped
     */
    private function isBeforeSyncStart(ExchangeConnection $connection, array $mapped): bool
    {
        if ($connection->sync_start_at === null) {
            return false;
        }

        return Carbon::parse($mapped['executed_at'])->lt($connection->sync_start_at);
    }
}
