<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExchangeConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'portfolio_id' => [
                'required',
                'integer',
                Rule::exists('portfolios', 'id')->where('user_id', $this->user()->id),
            ],
            'product_code' => ['required', 'string', Rule::in(['BTC_JPY'])],
            'api_key' => ['required', 'string', 'max:255'],
            'api_secret' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'portfolio_id' => '同期先ポートフォリオ',
            'product_code' => '商品コード',
            'api_key' => 'API Key',
            'api_secret' => 'API Secret',
        ];
    }
}
