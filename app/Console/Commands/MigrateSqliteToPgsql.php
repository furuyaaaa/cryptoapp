<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MigrateSqliteToPgsql extends Command
{
    protected $signature = 'db:migrate-sqlite-to-pgsql
        {--sqlite= : 読み込み元SQLiteファイルのパス（未指定なら SQLITE_LEGACY_DATABASE / database_path("database.sqlite")）}
        {--chunk=500 : 1回のINSERT件数}
        {--fresh : 実行前に pgsql 側で migrate:fresh を実行する}
        {--truncate : 各テーブルの既存行を移行前にTRUNCATEする（--freshを指定しない場合の推奨）}
        {--tables= : 移行するテーブルをカンマ区切りで指定（省略時は自動検出）}
        {--force : 確認プロンプトをスキップ}';

    protected $description = 'SQLite のデータを PostgreSQL に移行する（Schemaは pgsql 側のマイグレーションに準拠）';

    /**
     * 移行対象から除外するテーブル。
     * Laravel 本体の内部系や JobBatches / Cache / Sessions は
     * pgsql 側で再生成する前提で移行対象外とする。
     */
    private array $excludedTables = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
    ];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('デフォルト接続が pgsql ではありません。.env を確認してください。');

            return self::FAILURE;
        }

        $sqlitePath = $this->option('sqlite') ?: env('SQLITE_LEGACY_DATABASE') ?: database_path('database.sqlite');

        if (! file_exists($sqlitePath)) {
            $this->error("SQLiteファイルが見つかりません: {$sqlitePath}");

            return self::FAILURE;
        }

        config(['database.connections.sqlite_legacy.database' => $sqlitePath]);
        DB::purge('sqlite_legacy');

        $this->info("Source (sqlite): {$sqlitePath}");
        $this->info('Target (pgsql): '.DB::connection()->getDatabaseName());

        if (! $this->option('force') && ! $this->confirm('上記の設定で移行を実行します。よろしいですか？', true)) {
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->info('pgsql 側で migrate:fresh を実行します...');
            $this->call('migrate:fresh', ['--force' => true]);
        } else {
            $this->info('pgsql 側で migrate を実行します（未適用があれば適用）...');
            $this->call('migrate', ['--force' => true]);
        }

        $tables = $this->resolveTables();

        if ($tables === []) {
            $this->warn('移行対象のテーブルがありません。');

            return self::SUCCESS;
        }

        $this->info('対象テーブル: '.implode(', ', $tables));

        $totalRows = 0;
        $orderedTables = $this->orderTablesByDependency($tables);

        foreach ($orderedTables as $table) {
            $rows = $this->migrateTable($table);
            $totalRows += $rows;
        }

        $this->resetSequences($orderedTables);

        $this->newLine();
        $this->info("移行完了。合計 {$totalRows} 行を pgsql に投入しました。");

        return self::SUCCESS;
    }

    private function resolveTables(): array
    {
        if ($opt = $this->option('tables')) {
            return array_values(array_filter(array_map('trim', explode(',', $opt))));
        }

        $sqliteTables = collect(DB::connection('sqlite_legacy')
            ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->all();

        $pgTables = collect(DB::select(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema()"
        ))->pluck('table_name')->all();

        $common = array_intersect($sqliteTables, $pgTables);

        return array_values(array_diff($common, $this->excludedTables));
    }

    /**
     * 外部キー依存を考慮して、親テーブルから順に並べ替える。
     */
    private function orderTablesByDependency(array $tables): array
    {
        $priority = [
            'users' => 10,
            'exchanges' => 20,
            'assets' => 30,
            'portfolios' => 40,
            'asset_prices' => 50,
            'transactions' => 60,
        ];

        usort($tables, function ($a, $b) use ($priority) {
            $pa = $priority[$a] ?? 999;
            $pb = $priority[$b] ?? 999;

            return $pa <=> $pb ?: strcmp($a, $b);
        });

        return $tables;
    }

    private function migrateTable(string $table): int
    {
        $this->newLine();
        $this->line("[{$table}] 移行中...");

        if (! Schema::hasTable($table)) {
            $this->warn("  pgsql 側にテーブル {$table} が存在しません。スキップ。");

            return 0;
        }

        if ($this->option('truncate') && ! $this->option('fresh')) {
            DB::statement("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
            $this->line('  TRUNCATE 完了');
        }

        $boolColumns = $this->booleanColumns($table);
        $jsonColumns = $this->jsonColumns($table);

        $total = (int) DB::connection('sqlite_legacy')->table($table)->count();
        if ($total === 0) {
            $this->line('  空テーブル。スキップ。');

            return 0;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $inserted = 0;

        // SUPERUSER が必要な `SET session_replication_role` は使わず、
        // {@see orderTablesByDependency()} で親→子の順に流すことで
        // 外部キー制約に引っかからないようにしている。
        try {
            DB::beginTransaction();

            $hasId = collect(DB::connection('sqlite_legacy')->select("PRAGMA table_info(\"{$table}\")"))
                ->contains(fn ($c) => $c->name === 'id');

            $query = DB::connection('sqlite_legacy')->table($table);
            $query = $hasId ? $query->orderBy('id') : $query->orderBy(DB::raw('rowid'));

            $query->chunk($chunk, function ($rows) use ($table, $boolColumns, $jsonColumns, &$inserted, $bar) {
                $payload = $rows->map(function ($row) use ($boolColumns, $jsonColumns) {
                    $array = (array) $row;

                    foreach ($boolColumns as $col) {
                        if (array_key_exists($col, $array) && $array[$col] !== null) {
                            $array[$col] = (bool) $array[$col];
                        }
                    }

                    foreach ($jsonColumns as $col) {
                        if (array_key_exists($col, $array)
                            && is_string($array[$col])
                            && $array[$col] !== ''
                            && ! $this->isValidJson($array[$col])) {
                            // SQLite 側で壊れた値は JSON 文字列へエスケープ
                            $array[$col] = json_encode($array[$col]);
                        }
                    }

                    return $array;
                })->all();

                DB::table($table)->insert($payload);
                $inserted += count($payload);
                $bar->advance(count($payload));
            });

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            $this->error("  失敗: {$e->getMessage()}");
            throw $e;
        }

        $bar->finish();
        $this->newLine();
        $this->line("  -> {$inserted} 行を投入");

        return $inserted;
    }

    private function booleanColumns(string $table): array
    {
        return collect(DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ? AND data_type = 'boolean'",
            [$table]
        ))->pluck('column_name')->all();
    }

    private function jsonColumns(string $table): array
    {
        return collect(DB::select(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ? AND data_type IN ('json','jsonb')",
            [$table]
        ))->pluck('column_name')->all();
    }

    private function isValidJson(string $value): bool
    {
        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 各テーブルの id シーケンスを max(id)+1 に更新。
     */
    private function resetSequences(array $tables): void
    {
        $this->newLine();
        $this->info('シーケンスをリセットしています...');

        foreach ($tables as $table) {
            $seq = DB::selectOne(
                "SELECT pg_get_serial_sequence(?, 'id') AS seq",
                [$table]
            );

            if (! $seq || ! $seq->seq) {
                continue;
            }

            $max = DB::selectOne("SELECT COALESCE(MAX(id), 0) AS m FROM \"{$table}\"")->m ?? 0;
            $next = ((int) $max) + 1;

            DB::statement("SELECT setval(?, ?, false)", [$seq->seq, $next]);
            $this->line("  {$table}.id -> next {$next}");
        }
    }
}
