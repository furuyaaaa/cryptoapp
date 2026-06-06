<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class GmoCoinClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl = 'https://api.coin.z.com/private',
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function assets(): array
    {
        return $this->get('/v1/account/assets')['list'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latestExecutions(string $symbol, int $page = 1, int $count = 100): array
    {
        $data = $this->get('/v1/latestExecutions', [
            'symbol' => $symbol,
            'page' => $page,
            'count' => $count,
        ]);

        return $data['list'] ?? [];
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $attempts = 0;
        beginning:
        $attempts++;

        $response = $this->request('GET', $path)
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            Sleep::for($retryAfter > 0 ? $retryAfter : $attempts)->seconds();

            goto beginning;
        }

        return $this->unwrap($response->throw()->json());
    }

    private function request(string $method, string $path, string $body = ''): PendingRequest
    {
        $timestamp = (string) ((int) floor(microtime(true) * 1000));
        $signature = hash_hmac('sha256', $timestamp.$method.$path.$body, $this->apiSecret);

        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'API-KEY' => $this->apiKey,
                'API-TIMESTAMP' => $timestamp,
                'API-SIGN' => $signature,
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrap(array $payload): array
    {
        if (($payload['status'] ?? null) !== 0) {
            $messages = collect($payload['messages'] ?? [])
                ->map(fn ($message) => (string) ($message['message_string'] ?? $message['message_code'] ?? 'unknown'))
                ->filter()
                ->implode(', ');

            throw new RuntimeException('GMO Coin API error: '.($messages !== '' ? $messages : 'unknown'));
        }

        return $payload['data'] ?? [];
    }
}
