<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('symbol')) {
            $this->merge(['symbol' => strtoupper(trim((string) $this->input('symbol')))]);
        }
        if ($this->filled('coingecko_id')) {
            $this->merge(['coingecko_id' => strtolower(trim((string) $this->input('coingecko_id')))]);
        }
    }

    public function rules(): array
    {
        $assetId = $this->route('asset')?->id;

        return [
            'symbol' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('assets', 'symbol')->ignore($assetId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'coingecko_id' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('assets', 'coingecko_id')->ignore($assetId),
            ],
            'icon_url' => ['nullable', 'string', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'symbol.regex' => 'シンボルは英大文字と数字のみで入力してください。',
            'coingecko_id.regex' => 'CoinGecko ID は英小文字・数字・ハイフンのみで入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'symbol' => 'シンボル',
            'name' => '銘柄名',
            'coingecko_id' => 'CoinGecko ID',
            'icon_url' => 'アイコンURL',
        ];
    }
}
