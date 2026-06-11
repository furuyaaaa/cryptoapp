<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class KuCoinClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $apiPassphrase,
        private readonly string $baseUrl = 'https://api.kucoin.com',
        private readonly string $apiKeyVersion = '2',
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function symbols(): array
    {
        $payload = $this->publicGet('/api/v2/symbols');

        return $payload['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function apiKeyInfo(): array
    {
        $payload = $this->signedGet('/api/v1/user/api-key');

        return $payload['data'] ?? [];
    }

    /**
     * @return array{items: list<array<string, mixed>>, lastId: string|null}
     */
    public function fills(
        string $symbol,
        ?int $startAt = null,
        ?int $endAt = null,
        int $limit = 100,
        ?string $lastId = null,
    ): array {
        $query = [
            'symbol' => $symbol,
            'limit' => $limit,
        ];

        if ($startAt !== null) {
            $query['startAt'] = $startAt;
        }

        if ($endAt !== null) {
            $query['endAt'] = $endAt;
        }

        if ($lastId !== null) {
            $query['lastId'] = $lastId;
        }

        $payload = $this->signedGet('/api/v1/hf/fills', $query);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return [
            'items' => $data['items'] ?? [],
            'lastId' => isset($data['lastId']) ? (string) $data['lastId'] : null,
        ];
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function publicGet(string $path, array $query = []): array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            Sleep::for(1)->seconds();

            $response = Http::timeout(10)
                ->acceptJson()
                ->get($this->baseUrl.$path, $query);
        }

        $payload = $response->throw()->json();
        $this->assertSuccessfulPayload($payload);

        return $payload;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function signedGet(string $path, array $query = []): array
    {
        $attempts = 0;
        beginning:
        $attempts++;

        $queryString = http_build_query($query, '', '&');
        $endpoint = $path.($queryString !== '' ? '?'.$queryString : '');
        $timestamp = (string) (int) floor(microtime(true) * 1000);
        $signaturePayload = $timestamp.'GET'.$endpoint;
        $signature = base64_encode(hash_hmac('sha256', $signaturePayload, $this->apiSecret, true));
        $passphrase = base64_encode(hash_hmac('sha256', $this->apiPassphrase, $this->apiSecret, true));

        $response = $this->request($timestamp, $signature, $passphrase)
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            $resetMilliseconds = (int) $response->header('gw-ratelimit-reset', '0');
            $seconds = $retryAfter > 0 ? $retryAfter : max(1, (int) ceil($resetMilliseconds / 1000));
            Sleep::for($seconds)->seconds();

            goto beginning;
        }

        $payload = $response->json();
        $this->assertSuccessfulPayload($payload);
        $response->throw();

        return $payload;
    }

    /**
     * @param  mixed  $payload
     */
    private function assertSuccessfulPayload($payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $code = (string) ($payload['code'] ?? '200000');
        if ($code !== '200000') {
            $message = (string) ($payload['msg'] ?? $payload['message'] ?? 'unknown');

            throw new RuntimeException('KuCoin API error: '.$message);
        }
    }

    private function request(string $timestamp, string $signature, string $passphrase): PendingRequest
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'KC-API-KEY' => $this->apiKey,
                'KC-API-SIGN' => $signature,
                'KC-API-TIMESTAMP' => $timestamp,
                'KC-API-PASSPHRASE' => $passphrase,
                'KC-API-KEY-VERSION' => $this->apiKeyVersion,
                'Content-Type' => 'application/json',
            ]);
    }
}
