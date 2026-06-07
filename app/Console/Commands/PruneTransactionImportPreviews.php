<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneTransactionImportPreviews extends Command
{
    protected $signature = 'transaction-import-previews:prune
        {--hours=24 : Delete preview files older than this many hours}
        {--dry-run : Show how many files would be deleted without deleting them}';

    protected $description = 'Prune stale CSV files created for transaction import previews';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        if ($hours < 1) {
            $this->error('--hours must be at least 1.');

            return self::FAILURE;
        }

        $disk = Storage::disk();
        $threshold = now()->subHours($hours)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files('transaction-import-previews') as $file) {
            if (! str_ends_with($file, '.csv')) {
                continue;
            }

            $lastModified = $disk->lastModified($file);
            if ($lastModified > $threshold) {
                continue;
            }

            if (! $this->option('dry-run')) {
                $disk->delete($file);
            }

            $deleted++;
        }

        $message = $this->option('dry-run')
            ? "Would delete {$deleted} stale transaction import preview file(s)."
            : "Deleted {$deleted} stale transaction import preview file(s).";

        $this->info($message);

        return self::SUCCESS;
    }
}
