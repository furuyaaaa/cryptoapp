<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class BinanceClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl = 'https://api.binance.com',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function account(): array
    {
        return $this->signedGet('/api/v3/account');
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeInfo(): array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->get($this->baseUrl.'/api/v3/exchangeInfo');

        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            Sleep::for(1)->seconds();

            $response = Http::timeout(10)
                ->acceptJson()
                ->get($this->baseUrl.'/api/v3/exchangeInfo');
        }

        return $response->throw()->json();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function myTrades(string $symbol, ?int $startTime = null, ?int $endTime = null, int $limit = 1000): array
    {
        $query = [
            'symbol' => $symbol,
            'limit' => $limit,
        ];

        if ($startTime !== null) {
            $query['startTime'] = $startTime;
        }

        if ($endTime !== null) {
            $query['endTime'] = $endTime;
        }

        return $this->signedGet('/api/v3/myTrades', $query);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function signedGet(string $path, array $query = []): array
    {
        $attempts = 0;
        beginning:
        $attempts++;

        $query['timestamp'] = (int) floor(microtime(true) * 1000);
        $payload = http_build_query($query, '', '&');
        $query['signature'] = hash_hmac('sha256', $payload, $this->apiSecret);

        $response = $this->request()
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            Sleep::for($retryAfter > 0 ? $retryAfter : $attempts)->seconds();

            goto beginning;
        }

        $payload = $response->json();

        if (is_array($payload) && array_key_exists('code', $payload) && array_key_exists('msg', $payload)) {
            throw new RuntimeException('Binance API error: '.(string) $payload['msg']);
        }

        $response->throw();

        return $payload;
    }

    private function request(): PendingRequest
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'X-MBX-APIKEY' => $this->apiKey,
            ]);
    }
}
