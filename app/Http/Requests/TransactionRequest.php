<?php

namespace App\Http\Requests;

use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
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
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'exchange_id' => ['nullable', 'integer', 'exists:exchanges,id'],
            'type' => [
                'required',
                Rule::in([
                    Transaction::TYPE_BUY,
                    Transaction::TYPE_SELL,
                    Transaction::TYPE_TRANSFER_IN,
                    Transaction::TYPE_TRANSFER_OUT,
                ]),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'price_jpy' => ['required', 'numeric', 'gte:0'],
            'fee_jpy' => ['nullable', 'numeric', 'gte:0'],
            'executed_at' => ['required', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'portfolio_id.exists' => '指定されたポートフォリオは存在しないかアクセスできません。',
            'amount.gt' => '数量は0より大きい値を入力してください。',
            'executed_at.before_or_equal' => '取引日時は現在以前を指定してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'portfolio_id' => 'ポートフォリオ',
            'asset_id' => '銘柄',
            'exchange_id' => '取引所',
            'type' => '取引種別',
            'amount' => '数量',
            'price_jpy' => '取引単価',
            'fee_jpy' => '手数料',
            'executed_at' => '取引日時',
            'note' => 'メモ',
        ];
    }
}
