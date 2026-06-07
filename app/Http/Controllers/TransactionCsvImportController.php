<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionCsvImportRequest;
use App\Models\Transaction;
use App\Services\TransactionCsvImportService;
use App\Services\TransactionFormDataService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionCsvImportController extends Controller
{
    private const TEMPLATE_HEADERS = [
        '取引日時',
        '種別コード',
        '銘柄シンボル',
        '銘柄名',
        '数量',
        '単価(JPY)',
        '手数料(JPY)',
        '取引所',
        'ポートフォリオ',
        'メモ',
        'external_id',
    ];

    private const CSV_TEMPLATES = [
        'standard' => [
            'label' => '標準CSV',
            'exchange' => '',
            'external_id' => 'manual-20260601-001',
            'note' => '手動補完',
        ],
        'binance-japan' => [
            'label' => 'Binance Japan',
            'exchange' => 'Binance Japan',
            'external_id' => 'binance-japan-20260601-001',
            'note' => 'Binance Japan CSV補完',
        ],
        'bitflyer' => [
            'label' => 'bitFlyer',
            'exchange' => 'bitFlyer',
            'external_id' => 'bitflyer-20260601-001',
            'note' => 'bitFlyer CSV補完',
        ],
        'bitbank' => [
            'label' => 'bitbank',
            'exchange' => 'bitbank',
            'external_id' => 'bitbank-20260601-001',
            'note' => 'bitbank CSV補完',
        ],
        'coincheck' => [
            'label' => 'Coincheck',
            'exchange' => 'Coincheck',
            'external_id' => 'coincheck-20260601-001',
            'note' => 'Coincheck CSV補完',
        ],
        'gmo-coin' => [
            'label' => 'GMOコイン',
            'exchange' => 'GMOコイン',
            'external_id' => 'gmo-coin-20260601-001',
            'note' => 'GMOコイン CSV補完',
        ],
        'zaif' => [
            'label' => 'Zaif',
            'exchange' => 'Zaif',
            'external_id' => 'zaif-20260601-001',
            'note' => 'Zaif CSV補完',
        ],
    ];

    public function create(Request $request, TransactionFormDataService $formData): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('Transactions/Import', [
            ...$formData->build($request),
            'csvTemplates' => $this->csvTemplates(),
        ]);
    }

    public function template(Request $request, string $template): StreamedResponse
    {
        $this->authorize('create', Transaction::class);

        abort_unless(array_key_exists($template, self::CSV_TEMPLATES), 404);

        $filename = 'transaction_import_template_'.$template.'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
            'Pragma' => 'no-cache',
        ];

        return response()->stream(function () use ($template) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, Csv::sanitizeRow(self::TEMPLATE_HEADERS));
            fputcsv($handle, Csv::sanitizeRow($this->sampleRow($template)));

            fclose($handle);
        }, 200, $headers);
    }

    public function store(
        TransactionCsvImportRequest $request,
        TransactionCsvImportService $importer,
    ): RedirectResponse {
        $this->authorize('create', Transaction::class);

        if ($request->input('action') === 'preview') {
            $token = (string) Str::uuid();
            $path = $request->file('csv_file')->storeAs(
                'transaction-import-previews',
                $request->user()->id.'-'.$token.'.csv',
            );

            $result = $importer->preview(
                user: $request->user(),
                path: Storage::path($path),
                defaultPortfolioId: $request->integer('portfolio_id') ?: null,
            );

            if ($result['errors'] !== []) {
                Storage::delete($path);

                return back()
                    ->withInput()
                    ->with('import_errors', $result['errors']);
            }

            return back()
                ->withInput()
                ->with('import_preview', [
                    ...$result,
                    'token' => $token,
                    'portfolio_id' => $request->input('portfolio_id'),
                ]);
        }

        $path = 'transaction-import-previews/'.$request->user()->id.'-'.$request->input('import_token').'.csv';
        if (! Storage::exists($path)) {
            return back()
                ->withInput()
                ->with('import_errors', ['プレビュー済みCSVが見つかりません。もう一度CSVを選択してください。']);
        }

        $result = $importer->import(
            user: $request->user(),
            path: Storage::path($path),
            defaultPortfolioId: $request->integer('portfolio_id') ?: null,
        );

        Storage::delete($path);

        if ($result['errors'] !== []) {
            return back()
                ->withInput()
                ->with('import_errors', $result['errors']);
        }

        return redirect()
            ->route('transactions.index')
            ->with(
                'success',
                sprintf(
                    'CSVを取り込みました。登録 %d 件、重複スキップ %d 件。',
                    $result['imported'],
                    $result['skipped'],
                ),
            );
    }

    /**
     * @return list<array{slug: string, label: string}>
     */
    private function csvTemplates(): array
    {
        return array_map(
            fn (string $slug, array $template): array => [
                'slug' => $slug,
                'label' => $template['label'],
            ],
            array_keys(self::CSV_TEMPLATES),
            self::CSV_TEMPLATES,
        );
    }

    /**
     * @return list<string>
     */
    private function sampleRow(string $template): array
    {
        $metadata = self::CSV_TEMPLATES[$template];

        return [
            '2026-06-01 10:00:00',
            'buy',
            'BTC',
            'Bitcoin',
            '0.01',
            '10000000',
            '0',
            $metadata['exchange'],
            '',
            $metadata['note'],
            $metadata['external_id'],
        ];
    }
}
