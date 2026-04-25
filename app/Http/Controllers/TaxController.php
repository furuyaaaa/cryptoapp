<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TaxCalculationService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxController extends Controller
{
    public function __construct(private readonly TaxCalculationService $service)
    {
    }

    public function index(Request $request): Response
    {
        [$year, $method] = $this->params($request);

        $transactions = $this->userTransactions($request);

        $report = $this->service->calculate($transactions, $year, $method);
        $availableYears = $this->service->availableYears($transactions);

        return Inertia::render('Tax/Index', [
            'report' => $report,
            'filters' => [
                'year' => $year,
                'method' => $method,
            ],
            'options' => [
                'years' => $availableYears,
                'methods' => [
                    ['value' => TaxCalculationService::METHOD_MOVING_AVERAGE, 'label' => '移動平均法'],
                    ['value' => TaxCalculationService::METHOD_TOTAL_AVERAGE, 'label' => '総平均法'],
                ],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$year, $method] = $this->params($request);

        $transactions = $this->userTransactions($request);
        $report = $this->service->calculate($transactions, $year, $method);

        $methodLabel = $method === TaxCalculationService::METHOD_TOTAL_AVERAGE ? '総平均法' : '移動平均法';
        $filename = sprintf('tax_report_%d_%s.csv', $year, $method);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache',
        ];

        return response()->stream(function () use ($report, $methodLabel, $year) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, Csv::sanitizeRow(['# 対象年度', $year]));
            fputcsv($handle, Csv::sanitizeRow(['# 評価方法', $methodLabel]));
            fputcsv($handle, Csv::sanitizeRow(['# 出力日時', now()->format('Y-m-d H:i:s')]));
            fputcsv($handle, []);

            fputcsv($handle, Csv::sanitizeRow([
                '銘柄シンボル',
                '銘柄名',
                '期首数量',
                '期首取得価額(JPY)',
                '期中取得数量',
                '期中取得価額(JPY)',
                '平均取得単価(JPY)',
                '譲渡収入(JPY)',
                '譲渡原価(JPY)',
                '売却手数料(JPY)',
                '実現損益(JPY)',
                '売却回数',
                '期末数量',
            ]));

            foreach ($report['assets'] as $a) {
                fputcsv($handle, Csv::sanitizeRow([
                    $a['symbol'],
                    $a['name'],
                    $this->num($a['opening_amount'], 8),
                    $this->num($a['opening_cost'], 2),
                    $this->num($a['buy_amount_in_year'], 8),
                    $this->num($a['buy_cost_in_year'], 2),
                    $this->num($a['average_cost'], 2),
                    $this->num($a['proceeds'], 2),
                    $this->num($a['cost_of_sold'], 2),
                    $this->num($a['sell_fees'], 2),
                    $this->num($a['realized_gain'], 2),
                    $a['sell_count'],
                    $this->num($a['ending_amount'], 8),
                ]));
            }

            fputcsv($handle, []);
            fputcsv($handle, Csv::sanitizeRow([
                '合計',
                '',
                '',
                '',
                '',
                '',
                '',
                $this->num($report['totals']['proceeds'], 2),
                $this->num($report['totals']['cost_of_sold'], 2),
                $this->num($report['totals']['sell_fees'], 2),
                $this->num($report['totals']['realized_gain'], 2),
                $report['totals']['sell_count'],
                '',
            ]));

            fclose($handle);
        }, 200, $headers);
    }

    private function params(Request $request): array
    {
        $year = (int) $request->input('year', now()->format('Y'));
        $method = $request->input('method', TaxCalculationService::METHOD_MOVING_AVERAGE);

        if (! in_array($method, [TaxCalculationService::METHOD_MOVING_AVERAGE, TaxCalculationService::METHOD_TOTAL_AVERAGE], true)) {
            $method = TaxCalculationService::METHOD_MOVING_AVERAGE;
        }

        return [$year, $method];
    }

    private function userTransactions(Request $request)
    {
        $user = $request->user();

        return Transaction::query()
            ->whereHas('portfolio', fn ($q) => $q->where('user_id', $user->id))
            ->with(['asset:id,symbol,name,icon_url'])
            ->orderBy('executed_at')
            ->orderBy('id')
            ->get();
    }

    private function num(float $v, int $decimals): string
    {
        return number_format($v, $decimals, '.', '');
    }
}
