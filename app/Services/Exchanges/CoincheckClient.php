<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class CoincheckClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl = 'https://coincheck.com',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function account(): array
    {
        return $this->get('/api/accounts');
    }

    /**
     * @return array<string, string>
     */
    public function balance(): array
    {
        return $this->get('/api/accounts/balance');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function transactions(int $limit = 100, string $order = 'desc'): array
    {
        $data = $this->get('/api/exchange/orders/transactions_pagination', [
            'limit' => $limit,
            'order' => $order,
        ]);

        return $data['data'] ?? [];
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl.$path;
        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $attempts = 0;
        beginning:
        $attempts++;

        $response = $this->request($url)
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            Sleep::for($retryAfter > 0 ? $retryAfter : $attempts)->seconds();

            goto beginning;
        }

        return $this->unwrap($response->throw()->json());
    }

    private function request(string $url, string $body = ''): PendingRequest
    {
        $nonce = (string) ((int) floor(microtime(true) * 1000));
        $signature = hash_hmac('sha256', $nonce.$url.$body, $this->apiSecret);

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
        if (($payload['success'] ?? false) !== true) {
            $message = (string) ($payload['error'] ?? 'unknown');

            throw new RuntimeException("Coincheck API error: {$message}");
        }

        return $payload;
    }
}
