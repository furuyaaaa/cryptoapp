<?php

namespace App\Services\Exchanges;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;

class ZaifClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $baseUrl = 'https://api.zaif.jp',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return $this->privatePost(['method' => 'get_info2']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function currencyPairs(): array
    {
        $response = Http::timeout(10)
            ->acceptJson()
            ->get($this->baseUrl.'/api/1/currency_pairs/all');

        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            Sleep::for(1)->seconds();

            $response = Http::timeout(10)
                ->acceptJson()
                ->get($this->baseUrl.'/api/1/currency_pairs/all');
        }

        return $response->throw()->json();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function tradeHistory(string $currencyPair, ?int $since = null, int $count = 1000): array
    {
        $params = [
            'method' => 'trade_history',
            'currency_pair' => $currencyPair,
            'count' => $count,
            'order' => 'DESC',
        ];

        if ($since !== null) {
            $params['since'] = $since;
        }

        return $this->privatePost($params);
    }

    /**
     * @param  array<string, scalar>  $params
     * @return array<string, mixed>
     */
    private function privatePost(array $params): array
    {
        $attempts = 0;
        beginning:
        $attempts++;

        $body = ['nonce' => $this->nonce()] + $params;
        $response = $this->request($body)
            ->asForm()
            ->post($this->baseUrl.'/tapi', $body);

        if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempts < 4) {
            $retryAfter = (int) $response->header('Retry-After', '0');
            Sleep::for($retryAfter > 0 ? $retryAfter : $attempts)->seconds();

            goto beginning;
        }

        return $this->unwrap($response->throw()->json());
    }

    /**
     * @param  array<string, scalar>  $body
     */
    private function request(array $body): PendingRequest
    {
        $payload = http_build_query($body, '', '&');
        $signature = hash_hmac('sha512', $payload, $this->apiSecret);

        return Http::timeout(10)
            ->acceptJson()
            ->withHeaders([
                'key' => $this->apiKey,
                'sign' => $signature,
            ]);
    }

    private function nonce(): string
    {
        return sprintf('%.6F', microtime(true));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrap(array $payload): array
    {
        if (($payload['success'] ?? null) !== 1) {
            $message = (string) ($payload['return'] ?? 'unknown');

            throw new RuntimeException("Zaif API error: {$message}");
        }

        return (array) ($payload['return'] ?? []);
    }
}
