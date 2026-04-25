<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $assets = [
            ['symbol' => 'BTC',   'name' => 'Bitcoin',       'coingecko_id' => 'bitcoin'],
            ['symbol' => 'ETH',   'name' => 'Ethereum',      'coingecko_id' => 'ethereum'],
            ['symbol' => 'XRP',   'name' => 'XRP',           'coingecko_id' => 'ripple'],
            ['symbol' => 'USDT',  'name' => 'Tether',        'coingecko_id' => 'tether'],
            ['symbol' => 'BNB',   'name' => 'BNB',           'coingecko_id' => 'binancecoin'],
            ['symbol' => 'SOL',   'name' => 'Solana',        'coingecko_id' => 'solana'],
            ['symbol' => 'USDC',  'name' => 'USD Coin',      'coingecko_id' => 'usd-coin'],
            ['symbol' => 'ADA',   'name' => 'Cardano',       'coingecko_id' => 'cardano'],
            ['symbol' => 'DOGE',  'name' => 'Dogecoin',      'coingecko_id' => 'dogecoin'],
            ['symbol' => 'TRX',   'name' => 'TRON',          'coingecko_id' => 'tron'],
            ['symbol' => 'AVAX',  'name' => 'Avalanche',     'coingecko_id' => 'avalanche-2'],
            ['symbol' => 'SHIB',  'name' => 'Shiba Inu',     'coingecko_id' => 'shiba-inu'],
            ['symbol' => 'DOT',   'name' => 'Polkadot',      'coingecko_id' => 'polkadot'],
            ['symbol' => 'LINK',  'name' => 'Chainlink',     'coingecko_id' => 'chainlink'],
            ['symbol' => 'LTC',   'name' => 'Litecoin',      'coingecko_id' => 'litecoin'],
            ['symbol' => 'BCH',   'name' => 'Bitcoin Cash',  'coingecko_id' => 'bitcoin-cash'],
            ['symbol' => 'XLM',   'name' => 'Stellar',       'coingecko_id' => 'stellar'],
            ['symbol' => 'ATOM',  'name' => 'Cosmos',        'coingecko_id' => 'cosmos'],
            ['symbol' => 'NEAR',  'name' => 'NEAR Protocol', 'coingecko_id' => 'near'],
            ['symbol' => 'POL',   'name' => 'Polygon (POL)', 'coingecko_id' => 'polygon-ecosystem-token'],
        ];

        foreach ($assets as $data) {
            Asset::updateOrCreate(
                ['symbol' => $data['symbol']],
                [
                    'name' => $data['name'],
                    'coingecko_id' => $data['coingecko_id'],
                ]
            );
        }
    }
}
