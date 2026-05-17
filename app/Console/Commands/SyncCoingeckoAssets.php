<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\CoinGeckoService;
use Illuminate\Console\Command;
use Throwable;

class SyncCoingeckoAssets extends Command
{
    protected $signature = 'coingecko:sync-assets {--dry-run : API から取得した件数のみ表示し DB は更新しない}';

    protected $description = 'CoinGecko /coins/list を取得し assets を coingecko_id 単位で upsert する';

    public function handle(CoinGeckoService $coinGecko): int
    {
        try {
            $list = $coinGecko->fetchCoinList();
        } catch (Throwable $e) {
            $this->error('CoinGecko 取得に失敗: '.$e->getMessage());

            return self::FAILURE;
        }

        $count = count($list);
        $this->info(sprintf('CoinGecko 銘柄リスト: %d 件', $count));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $now = now();
        $chunks = array_chunk($list, 250);
        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as $chunk) {
            $rows = [];
            foreach ($chunk as $row) {
                if (! isset($row['id'], $row['name'])) {
                    continue;
                }
                $cgId = strtolower((string) $row['id']);
                $symbol = $this->normalizeSymbol((string) ($row['symbol'] ?? ''), $cgId);
                $name = mb_substr((string) $row['name'], 0, 255);

                $rows[] = [
                    'symbol' => $symbol,
                    'name' => $name,
                    'coingecko_id' => $cgId,
                    'icon_url' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                Asset::upsert($rows, ['coingecko_id'], ['symbol', 'name', 'updated_at']);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('upsert 完了。');

        return self::SUCCESS;
    }

    private function normalizeSymbol(string $raw, string $coingeckoId): string
    {
        $cleaned = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($cleaned !== '') {
            return mb_substr($cleaned, 0, 30);
        }

        return 'U'.strtoupper(substr(sha1($coingeckoId), 0, 8));
    }
}
