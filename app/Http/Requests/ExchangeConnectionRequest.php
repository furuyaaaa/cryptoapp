<?php

namespace App\Http\Requests;

use App\Services\Exchanges\BinanceExecutionSyncService;
use App\Services\Exchanges\BitbankExecutionSyncService;
use App\Services\Exchanges\BitFlyerExecutionSyncService;
use App\Services\Exchanges\CoincheckExecutionSyncService;
use App\Services\Exchanges\GmoCoinExecutionSyncService;
use App\Services\Exchanges\ZaifExecutionSyncService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'exchange_code' => ['required', 'string', Rule::in(['bitflyer', 'bitbank', 'coincheck', 'gmo_coin', 'zaif', 'binance'])],
            'product_code' => ['required', 'string', Rule::in([
                BitFlyerExecutionSyncService::ALL_SPOT_JPY,
                BitbankExecutionSyncService::ALL_JPY_PAIRS,
                CoincheckExecutionSyncService::ALL_JPY_PAIRS,
                GmoCoinExecutionSyncService::ALL_SPOT_SYMBOLS,
                ZaifExecutionSyncService::ALL_JPY_PAIRS,
                BinanceExecutionSyncService::ALL_JPY_SYMBOLS,
                'BTC_JPY',
                'btc_jpy',
                'BTC',
                'BTCJPY',
            ])],
            'api_key' => ['required', 'string', 'max:255'],
            'api_secret' => ['required', 'string', 'max:255'],
            'sync_start_mode' => ['required', 'string', Rule::in(['today', 'all', 'custom'])],
            'sync_start_date' => ['nullable', 'required_if:sync_start_mode,custom', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'portfolio_id' => '同期先ポートフォリオ',
            'exchange_code' => '取引所',
            'product_code' => '商品コード',
            'api_key' => 'API Key',
            'api_secret' => 'API Secret',
            'sync_start_mode' => '同期開始',
            'sync_start_date' => '同期開始日',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $exchangeCode = $this->input('exchange_code');
            $productCode = $this->input('product_code');

            $valid = match ($exchangeCode) {
                'bitflyer' => in_array($productCode, [
                    BitFlyerExecutionSyncService::ALL_SPOT_JPY,
                    'BTC_JPY',
                ], true),
                'bitbank' => in_array($productCode, [
                    BitbankExecutionSyncService::ALL_JPY_PAIRS,
                    'btc_jpy',
                ], true),
                'coincheck' => in_array($productCode, [
                    CoincheckExecutionSyncService::ALL_JPY_PAIRS,
                    'btc_jpy',
                ], true),
                'gmo_coin' => in_array($productCode, [
                    GmoCoinExecutionSyncService::ALL_SPOT_SYMBOLS,
                    'BTC',
                ], true),
                'zaif' => in_array($productCode, [
                    ZaifExecutionSyncService::ALL_JPY_PAIRS,
                    'btc_jpy',
                ], true),
                'binance' => in_array($productCode, [
                    BinanceExecutionSyncService::ALL_JPY_SYMBOLS,
                    'BTCJPY',
                ], true),
                default => false,
            };

            if (! $valid) {
                $validator->errors()->add('product_code', '取引所に対応した商品コードを選択してください。');
            }
        });
    }
}
