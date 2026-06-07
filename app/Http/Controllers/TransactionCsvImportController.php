<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionCsvImportRequest;
use App\Models\Transaction;
use App\Services\TransactionCsvImportService;
use App\Services\TransactionFormDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $result = $importer->import(
            user: $request->user(),
            path: $request->file('csv_file')->getRealPath(),
            defaultPortfolioId: $request->integer('portfolio_id') ?: null,
        );

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
