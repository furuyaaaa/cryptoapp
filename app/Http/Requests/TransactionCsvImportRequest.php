<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionCsvImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['preview', 'import'])],
            'portfolio_id' => [
                'nullable',
                'integer',
                Rule::exists('portfolios', 'id')->where('user_id', $this->user()->id),
            ],
            'csv_file' => ['required_if:action,preview', 'file', 'mimes:csv,txt', 'max:2048'],
            'import_token' => ['required_if:action,import', 'nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'portfolio_id' => '既定のポートフォリオ',
            'csv_file' => 'CSVファイル',
            'import_token' => 'インポート確認トークン',
        ];
    }
}
