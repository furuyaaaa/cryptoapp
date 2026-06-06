<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class BitFlyerClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl = 'https://api.bitflyer.com',
    ) {}

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->get('/v1/me/getpermissions');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function executions(string $productCode = 'BTC_JPY', int $count = 100, ?int $before = null): array
    {
        $query = [
            'product_code' => $productCode,
            'count' => $count,
        ];

        if ($before !== null) {
            $query['before'] = $before;
        }

        return $this->get('/v1/me/getexecutions', $query);
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $requestPath = $path;
        if ($query !== []) {
            $requestPath .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $attempts = 0;
        beginning:
        $attempts++;

        $response = $this->request('GET', $requestPath, '')
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            Sleep::for($retryAfter > 0 ? $retryAfter : $attempts)->seconds();

            goto beginning;
        }

        return $response->throw()->json();
    }

    private function request(string $method, string $requestPath, string $body): PendingRequest
    {
        $timestamp = sprintf('%.6F', microtime(true));
        $sign = hash_hmac('sha256', $timestamp.$method.$requestPath.$body, $this->apiSecret);

        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'ACCESS-KEY' => $this->apiKey,
                'ACCESS-TIMESTAMP' => $timestamp,
                'ACCESS-SIGN' => $sign,
            ]);
    }
}
