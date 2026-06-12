<?php

use App\Services\Exchanges\CoinbaseClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

if (! function_exists('coinbaseTestPrivateKey')) {
    function coinbaseTestPrivateKey(): string
    {
        return <<<'PEM'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIN0ekMQxWGuh23oKDf37MMxwNw12vL1UAxEUmfOxH1EUoAoGCCqGSM49
AwEHoUQDQgAEil5n06Q+bAtqcTvj4v56s5ReZ5H/LfMaCKG2Rq9XoR0uhNxZS37B
Rp2EzUfXijbgnDPI0IgccBatG7eJXZdTNw==
-----END EC PRIVATE KEY-----
PEM;
    }
}

if (! function_exists('coinbaseJwtPart')) {
    function coinbaseJwtPart(string $jwt, int $index): array
    {
        $part = explode('.', $jwt)[$index];
        $part .= str_repeat('=', (4 - strlen($part) % 4) % 4);

        return json_decode(base64_decode(strtr($part, '-_', '+/')), true);
    }
}

test('Coinbase signed request includes ES256 bearer JWT for fills', function () {
    Http::fake([
        'https://api.coinbase.com/api/v3/brokerage/orders/historical/fills*' => Http::response([
            'fills' => [],
            'cursor' => null,
        ], 200),
    ]);

    $client = new CoinbaseClient('organizations/org/apiKeys/key', coinbaseTestPrivateKey());
    $client->fills('BTC-USD', '2026-01-01T00:00:00+00:00', null, 100, 'abc');

    Http::assertSent(function (Request $request) {
        $authorization = $request->header('Authorization')[0] ?? '';
        $jwt = substr($authorization, strlen('Bearer '));
        $header = coinbaseJwtPart($jwt, 0);
        $payload = coinbaseJwtPart($jwt, 1);
        $query = parse_url($request->url(), PHP_URL_QUERY) ?: '';

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.coinbase.com/api/v3/brokerage/orders/historical/fills')
            && str_starts_with($authorization, 'Bearer ')
            && ($header['alg'] ?? null) === 'ES256'
            && ($header['kid'] ?? null) === 'organizations/org/apiKeys/key'
            && isset($header['nonce'])
            && ($payload['iss'] ?? null) === 'cdp'
            && ($payload['sub'] ?? null) === 'organizations/org/apiKeys/key'
            && ($payload['uri'] ?? null) === 'GET api.coinbase.com/api/v3/brokerage/orders/historical/fills'
            && str_contains($query, 'product_ids=BTC-USD')
            && str_contains($query, 'product_types=SPOT')
            && str_contains($query, 'start_sequence_timestamp=2026-01-01T00%3A00%3A00%2B00%3A00')
            && str_contains($query, 'cursor=abc');
    });
});

test('Coinbase client retries rate limited responses', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push(['message' => 'Too Many Requests'], 429, ['Retry-After' => '1'])
        ->push(['accounts' => []], 200);

    $client = new CoinbaseClient('organizations/org/apiKeys/key', coinbaseTestPrivateKey());
    $client->accounts();

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});
