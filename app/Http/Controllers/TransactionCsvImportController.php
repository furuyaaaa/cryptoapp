<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionCsvImportRequest;
use App\Models\Transaction;
use App\Services\TransactionCsvImportService;
use App\Services\TransactionFormDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCsvImportController extends Controller
{
    public function create(Request $request, TransactionFormDataService $formData): Response
    {
        $this->authorize('create', Transaction::class);

        return Inertia::render('Transactions/Import', $formData->build($request));
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
}
