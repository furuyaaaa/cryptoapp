<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class BitgetClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $apiPassphrase,
        private readonly string $baseUrl = 'https://api.bitget.com',
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function symbols(?string $symbol = null): array
    {
        $query = [];
        if ($symbol !== null) {
            $query['symbol'] = $symbol;
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->get($this->baseUrl.'/api/v2/spot/public/symbols', $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            Sleep::for(1)->seconds();

            $response = Http::timeout(10)
                ->acceptJson()
                ->get($this->baseUrl.'/api/v2/spot/public/symbols', $query);
        }

        $payload = $response->throw()->json();
        $this->assertSuccessfulPayload($payload);

        return $payload['data'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assets(?string $coin = null): array
    {
        $query = ['assetType' => 'all'];
        if ($coin !== null) {
            $query['coin'] = $coin;
        }

        $payload = $this->signedGet('/api/v2/spot/account/assets', $query);

        return $payload['data'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fills(
        string $symbol,
        ?int $startTime = null,
        ?int $endTime = null,
        int $limit = 100,
        ?string $idLessThan = null,
    ): array {
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

        if ($idLessThan !== null) {
            $query['idLessThan'] = $idLessThan;
        }

        $payload = $this->signedGet('/api/v2/spot/trade/fills', $query);

        return $payload['data'] ?? [];
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
        $timestamp = (string) (int) floor(microtime(true) * 1000);
        $signaturePayload = $timestamp.'GET'.$path.($queryString !== '' ? '?'.$queryString : '');
        $signature = base64_encode(hash_hmac('sha256', $signaturePayload, $this->apiSecret, true));

        $response = $this->request($timestamp, $signature)
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            Sleep::for($retryAfter > 0 ? $retryAfter : $attempts)->seconds();

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

        $code = (string) ($payload['code'] ?? '00000');
        if ($code !== '00000') {
            $message = (string) ($payload['msg'] ?? $payload['message'] ?? 'unknown');

            throw new RuntimeException('Bitget API error: '.$message);
        }
    }

    private function request(string $timestamp, string $signature): PendingRequest
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'ACCESS-KEY' => $this->apiKey,
                'ACCESS-SIGN' => $signature,
                'ACCESS-PASSPHRASE' => $this->apiPassphrase,
                'ACCESS-TIMESTAMP' => $timestamp,
                'locale' => 'en-US',
                'Content-Type' => 'application/json',
            ]);
    }
}
