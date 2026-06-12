<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class CoinbaseClient
{
    public function __construct(
        private readonly string $apiKeyName,
        private readonly string $apiSecret,
        private readonly string $baseUrl = 'https://api.coinbase.com',
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function accounts(): array
    {
        $payload = $this->signedGet('/api/v3/brokerage/accounts', ['limit' => 250]);

        return $payload['accounts'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function products(): array
    {
        $payload = $this->signedGet('/api/v3/brokerage/products', ['limit' => 250]);

        return $payload['products'] ?? [];
    }

    /**
     * @return array{items: list<array<string, mixed>>, cursor: string|null}
     */
    public function fills(
        string $productId,
        ?string $startSequenceTimestamp = null,
        ?string $endSequenceTimestamp = null,
        int $limit = 100,
        ?string $cursor = null,
    ): array {
        $query = [
            'product_ids' => $productId,
            'product_types' => 'SPOT',
            'sort_by' => 'TRADE_TIME',
            'limit' => $limit,
        ];

        if ($startSequenceTimestamp !== null) {
            $query['start_sequence_timestamp'] = $startSequenceTimestamp;
        }

        if ($endSequenceTimestamp !== null) {
            $query['end_sequence_timestamp'] = $endSequenceTimestamp;
        }

        if ($cursor !== null) {
            $query['cursor'] = $cursor;
        }

        $payload = $this->signedGet('/api/v3/brokerage/orders/historical/fills', $query);

        return [
            'items' => $payload['fills'] ?? [],
            'cursor' => isset($payload['cursor']) ? (string) $payload['cursor'] : null,
        ];
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

        $response = $this->request('GET', $path)
            ->get($this->baseUrl.$path, $query);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $seconds = max(1, (int) $response->header('Retry-After', '1'));
            Sleep::for($seconds)->seconds();

            goto beginning;
        }

        $payload = $response->json();
        $response->throw();

        if (! is_array($payload)) {
            throw new RuntimeException('Coinbase API returned an invalid response.');
        }

        return $payload;
    }

    private function request(string $method, string $path): PendingRequest
    {
        return Http::timeout(10)
            ->acceptJson()
            ->withToken($this->jwt($method, $path));
    }

    private function jwt(string $method, string $path): string
    {
        $secret = str_replace('\n', "\n", trim($this->apiSecret));
        $privateKey = openssl_pkey_get_private($secret);

        if ($privateKey === false) {
            throw new RuntimeException('Coinbase API Secret must be an EC private key PEM.');
        }

        $host = parse_url($this->baseUrl, PHP_URL_HOST) ?: 'api.coinbase.com';
        $now = time();
        $header = [
            'typ' => 'JWT',
            'alg' => 'ES256',
            'kid' => $this->apiKeyName,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $payload = [
            'sub' => $this->apiKeyName,
            'iss' => 'cdp',
            'nbf' => $now,
            'exp' => $now + 120,
            'uri' => strtoupper($method).' '.$host.$path,
        ];

        $signingInput = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
            .'.'.$this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign Coinbase JWT.');
        }

        return $signingInput.'.'.$this->base64UrlEncode($this->derSignatureToJose($signature));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function derSignatureToJose(string $signature): string
    {
        $offset = 0;
        if (ord($signature[$offset++]) !== 0x30) {
            throw new RuntimeException('Invalid Coinbase JWT signature.');
        }

        $this->readLength($signature, $offset);
        $r = $this->readInteger($signature, $offset);
        $s = $this->readInteger($signature, $offset);

        return $this->normalizeInteger($r).$this->normalizeInteger($s);
    }

    private function readInteger(string $der, int &$offset): string
    {
        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid Coinbase JWT signature integer.');
        }

        $length = $this->readLength($der, $offset);
        $value = substr($der, $offset, $length);
        $offset += $length;

        return $value;
    }

    private function readLength(string $der, int &$offset): int
    {
        $length = ord($der[$offset++]);

        if (($length & 0x80) === 0) {
            return $length;
        }

        $bytes = $length & 0x7F;
        $length = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | ord($der[$offset++]);
        }

        return $length;
    }

    private function normalizeInteger(string $value): string
    {
        $value = ltrim($value, "\x00");

        if (strlen($value) > 32) {
            $value = substr($value, -32);
        }

        return str_pad($value, 32, "\x00", STR_PAD_LEFT);
    }
}
