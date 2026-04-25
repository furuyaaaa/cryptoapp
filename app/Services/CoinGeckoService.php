<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * CoinGecko 公開 API のクライアント。
 *
 * 信頼性向上のため以下を担当する:
 *  - 429 (Too Many Requests) / 5xx のみ再試行対象にする (4xx の他は即失敗)
 *  - 指数バックオフ + ランダムジッターで再試行間隔を広げ、連打で悪化するのを防ぐ
 *  - Retry-After ヘッダが付いていればそれを優先
 *  - 成功レスポンスを短時間 (デフォルト 60 秒) キャッシュし、短時間内の重複呼び出しを抑制
 *
 * テスト: Http::fake() でスタブを張ると Retry-After を読む分岐も含めて検証可能。
 */
class CoinGeckoService
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';
    private const VS_CURRENCIES = 'jpy,usd';
    private const CHUNK_SIZE = 100;
    private const TIMEOUT_SECONDS = 15;
    /** リトライ最大回数（1 回目の失敗後からカウント） */
    private const MAX_RETRIES = 3;
    /** 初期バックオフ (ms)。以後は 2x ずつ伸ばす: 1000 → 2000 → 4000 */
    private const BASE_BACKOFF_MS = 1000;
    /** ジッター最大値 (ms)。バックオフに 0〜この値を足す */
    private const JITTER_MAX_MS = 500;
    /** Retry-After が秒数で指定された場合の上限 (ms)。これを超える場合は上限を採用し諦める */
    private const RETRY_AFTER_CAP_MS = 30_000;
    /** 成功レスポンスのキャッシュ寿命 (秒)。価格取得用 */
    private const PRICE_CACHE_TTL_SECONDS = 60;
    /** マーケット (アイコン等) は変化が遅いので長めにキャッシュ */
    private const MARKET_CACHE_TTL_SECONDS = 3600;

    /**
     * 指定された CoinGecko ID 群の現在価格 (jpy, usd) を返す。
     *
     * @param  array<string>  $coingeckoIds
     * @return array<string, array{jpy: float, usd: float}>
     *
     * @throws ConnectionException|RequestException
     */
    public function fetchPrices(array $coingeckoIds): array
    {
        $prices = [];

        foreach (array_chunk($coingeckoIds, self::CHUNK_SIZE) as $chunk) {
            $cacheKey = 'coingecko:prices:'.md5(implode(',', $chunk));

            $chunkPrices = Cache::remember($cacheKey, self::PRICE_CACHE_TTL_SECONDS, function () use ($chunk) {
                $response = $this->requestWithRetry('/simple/price', [
                    'ids' => implode(',', $chunk),
                    'vs_currencies' => self::VS_CURRENCIES,
                ]);

                $collected = [];
                foreach ($response->json() ?? [] as $id => $data) {
                    if (! isset($data['jpy'], $data['usd'])) {
                        Log::warning('CoinGecko: incomplete price data', ['id' => $id, 'data' => $data]);
                        continue;
                    }

                    $collected[$id] = [
                        'jpy' => (float) $data['jpy'],
                        'usd' => (float) $data['usd'],
                    ];
                }

                return $collected;
            });

            $prices = $prices + $chunkPrices;
        }

        return $prices;
    }

    /**
     * 指定された CoinGecko ID 群のマーケット情報 (主にアイコン URL) を返す。
     *
     * @param  array<string>  $coingeckoIds
     * @return array<string, array{image: ?string}>
     *
     * @throws ConnectionException|RequestException
     */
    public function fetchMarkets(array $coingeckoIds): array
    {
        $markets = [];

        foreach (array_chunk($coingeckoIds, 250) as $chunk) {
            $cacheKey = 'coingecko:markets:'.md5(implode(',', $chunk));

            $chunkMarkets = Cache::remember($cacheKey, self::MARKET_CACHE_TTL_SECONDS, function () use ($chunk) {
                $response = $this->requestWithRetry('/coins/markets', [
                    'vs_currency' => 'usd',
                    'ids' => implode(',', $chunk),
                    'per_page' => 250,
                    'page' => 1,
                    'sparkline' => 'false',
                    'price_change_percentage' => '24h',
                ]);

                $collected = [];
                foreach ($response->json() ?? [] as $row) {
                    if (! isset($row['id'])) {
                        continue;
                    }
                    $collected[$row['id']] = [
                        'image' => $row['image'] ?? null,
                    ];
                }

                return $collected;
            });

            $markets = $markets + $chunkMarkets;
        }

        return $markets;
    }

    /**
     * 指定エンドポイントに対してリトライ付き GET を実行する。
     *
     * - 2xx: 即返す
     * - 429 / 5xx: Retry-After 優先、なければ指数バックオフ + ジッターで再試行
     * - その他 4xx: 即 throw (400/401/403/404 等は再試行しても無駄)
     *
     * @param  array<string, mixed>  $query
     *
     * @throws ConnectionException|RequestException
     */
    protected function requestWithRetry(string $path, array $query): Response
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= self::MAX_RETRIES) {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->acceptJson()
                    ->get(self::BASE_URL.$path, $query);

                // 成功
                if ($response->successful()) {
                    return $response;
                }

                // 再試行しない 4xx
                if ($response->clientError() && $response->status() !== 429) {
                    return $response->throw();
                }

                // 429 / 5xx はリトライ対象
                if ($attempt >= self::MAX_RETRIES) {
                    Log::error('CoinGecko: exhausted retries', [
                        'path' => $path,
                        'status' => $response->status(),
                    ]);

                    return $response->throw();
                }

                $sleepMs = $this->computeSleepMs($response, $attempt);
                Log::warning('CoinGecko: transient error, will retry', [
                    'path' => $path,
                    'status' => $response->status(),
                    'attempt' => $attempt + 1,
                    'sleep_ms' => $sleepMs,
                ]);
                Sleep::usleep($sleepMs * 1000);
            } catch (ConnectionException $e) {
                // タイムアウト等は再試行対象
                $lastException = $e;
                if ($attempt >= self::MAX_RETRIES) {
                    Log::error('CoinGecko: connection failed after retries', [
                        'path' => $path,
                        'message' => $e->getMessage(),
                    ]);
                    throw $e;
                }
                $sleepMs = $this->computeBackoffMs($attempt);
                Log::warning('CoinGecko: connection error, will retry', [
                    'path' => $path,
                    'attempt' => $attempt + 1,
                    'sleep_ms' => $sleepMs,
                ]);
                Sleep::usleep($sleepMs * 1000);
            }

            $attempt++;
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        // ここには来ないが型安全のため
        throw new \RuntimeException('CoinGecko request failed without a response.');
    }

    /**
     * レスポンスの Retry-After を優先し、無ければ指数バックオフを返す (ms)。
     */
    protected function computeSleepMs(Response $response, int $attempt): int
    {
        $retryAfter = $response->header('Retry-After');
        if ($retryAfter !== null && $retryAfter !== '') {
            // 秒数形式 (例: "30") を優先サポート。HTTP-date 形式は本番で滅多に来ないためベストエフォート。
            if (is_numeric($retryAfter)) {
                $ms = ((int) $retryAfter) * 1000;

                return min($ms, self::RETRY_AFTER_CAP_MS);
            }

            $timestamp = strtotime((string) $retryAfter);
            if ($timestamp !== false) {
                $ms = max(0, ($timestamp - time()) * 1000);

                return min($ms, self::RETRY_AFTER_CAP_MS);
            }
        }

        return $this->computeBackoffMs($attempt);
    }

    /**
     * 指数バックオフ + ランダムジッター (ms)。
     */
    protected function computeBackoffMs(int $attempt): int
    {
        $base = self::BASE_BACKOFF_MS * (2 ** $attempt);

        return $base + random_int(0, self::JITTER_MAX_MS);
    }
}
