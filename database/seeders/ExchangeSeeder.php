<?php

namespace Database\Seeders;

use App\Models\Exchange;
use Illuminate\Database\Seeder;

class ExchangeSeeder extends Seeder
{
    public function run(): void
    {
        $exchanges = [
            ['code' => 'bitflyer',     'name' => 'bitFlyer',     'country' => 'JP'],
            ['code' => 'coincheck',    'name' => 'Coincheck',    'country' => 'JP'],
            ['code' => 'gmo_coin',     'name' => 'GMOコイン',     'country' => 'JP'],
            ['code' => 'bitbank',      'name' => 'bitbank',      'country' => 'JP'],
            ['code' => 'dmm_bitcoin',  'name' => 'DMM Bitcoin',  'country' => 'JP'],
            ['code' => 'sbi_vc_trade', 'name' => 'SBI VC Trade', 'country' => 'JP'],
            ['code' => 'bitpoint',     'name' => 'BITPOINT',     'country' => 'JP'],
            ['code' => 'zaif',         'name' => 'Zaif',         'country' => 'JP'],
            ['code' => 'binance',      'name' => 'Binance',      'country' => null],
            ['code' => 'coinbase',     'name' => 'Coinbase',     'country' => 'US'],
            ['code' => 'kraken',       'name' => 'Kraken',       'country' => 'US'],
            ['code' => 'bybit',        'name' => 'Bybit',        'country' => null],
            ['code' => 'okx',          'name' => 'OKX',          'country' => null],
            ['code' => 'bitget',       'name' => 'Bitget',       'country' => null],
            ['code' => 'kucoin',       'name' => 'KuCoin',       'country' => null],
            ['code' => 'gateio',       'name' => 'Gate.io',      'country' => null],
            ['code' => 'wallet',       'name' => 'ウォレット',     'country' => null],
            ['code' => 'other',        'name' => 'その他',        'country' => null],
        ];

        foreach ($exchanges as $data) {
            Exchange::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'country' => $data['country'],
                ]
            );
        }
    }
}
