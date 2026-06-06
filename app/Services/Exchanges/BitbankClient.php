<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class BitbankClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl = 'https://api.bitbank.cc',
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function pairs(): array
    {
        return $this->publicGet('/v1/spot/pairs')['pairs'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tradeHistory(string $pair, int $count = 100, string $order = 'desc'): array
    {
        $data = $this->get('/v1/user/spot/trade_history', [
            'pair' => $pair,
            'count' => $count,
            'order' => $order,
        ]);

        return $data['trades'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assets(): array
    {
        return $this->get('/v1/user/assets')['assets'] ?? [];
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
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

        $response = $this->request($requestPath)
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            Sleep::for($retryAfter > 0 ? $retryAfter : $attempts)->seconds();

            goto beginning;
        }

        return $this->unwrap($response->throw()->json());
    }

    /**
     * @return array<string, mixed>
     */
    private function publicGet(string $path): array
    {
        $attempts = 0;
        beginning:
        $attempts++;

        $response = Http::timeout(10)
            ->acceptJson()
            ->get($this->baseUrl.$path);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            Sleep::for($retryAfter > 0 ? $retryAfter : $attempts)->seconds();

            goto beginning;
        }

        return $this->unwrap($response->throw()->json());
    }

    private function request(string $requestPath): PendingRequest
    {
        $nonce = (string) ((int) floor(microtime(true) * 1000));
        $signature = hash_hmac('sha256', $nonce.$requestPath, $this->apiSecret);

        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'ACCESS-KEY' => $this->apiKey,
                'ACCESS-NONCE' => $nonce,
                'ACCESS-SIGNATURE' => $signature,
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrap(array $payload): array
    {
        if (($payload['success'] ?? 0) !== 1) {
            $code = data_get($payload, 'data.code', 'unknown');

            throw new RuntimeException("bitbank API error: {$code}");
        }

        return $payload['data'] ?? [];
    }
}
