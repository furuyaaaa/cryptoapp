<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Exchange;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TransactionCsvImportService
{
    private const MAX_ROWS = 1000;

    private const PREVIEW_ROWS = 20;

    /**
     * @return array{total: int, importable: int, skipped: int, create_assets: int, errors: list<string>, rows: list<array<string, mixed>>}
     */
    public function preview(User $user, string $path, ?int $defaultPortfolioId = null): array
    {
        [$rows, $errors] = $this->readRows($path);
        if ($errors !== []) {
            return ['total' => 0, 'importable' => 0, 'skipped' => 0, 'create_assets' => 0, 'errors' => $errors, 'rows' => []];
        }

        $context = $this->context($user);
        $previewRows = [];
        $importable = 0;
        $skipped = 0;
        $createAssets = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $mapped = $this->prepareRow($row, $line, $context, $defaultPortfolioId, false);

            if (isset($mapped['error'])) {
                $errors[] = $mapped['error'];

                continue;
            }

            if ($mapped['will_create_asset']) {
                $createAssets[$mapped['preview']['symbol']] = true;
            }

            if ($this->duplicateExists($mapped['data']['external_source'], $mapped['data']['external_id'])) {
                $skipped++;
                $mapped['preview']['status'] = 'skip';
            } else {
                $importable++;
                $mapped['preview']['status'] = 'import';
            }

            if (count($previewRows) < self::PREVIEW_ROWS) {
                $previewRows[] = $mapped['preview'];
            }
        }

        return [
            'total' => count($rows),
            'importable' => $errors === [] ? $importable : 0,
            'skipped' => $errors === [] ? $skipped : 0,
            'create_assets' => $errors === [] ? count($createAssets) : 0,
            'errors' => $errors,
            'rows' => $errors === [] ? $previewRows : [],
        ];
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function import(User $user, string $path, ?int $defaultPortfolioId = null): array
    {
        [$rows, $errors] = $this->readRows($path);
        if ($errors !== []) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => $errors];
        }

        DB::beginTransaction();

        try {
            $context = $this->context($user);
            $prepared = [];

            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $mapped = $this->prepareRow($row, $line, $context, $defaultPortfolioId, true);

                if (isset($mapped['error'])) {
                    $errors[] = $mapped['error'];

                    continue;
                }

                $prepared[] = $mapped['data'];
            }

            if ($errors !== []) {
                DB::rollBack();

                return ['imported' => 0, 'skipped' => 0, 'errors' => $errors];
            }

            $imported = 0;
            $skipped = 0;
            foreach ($prepared as $data) {
                if ($this->duplicateExists($data['external_source'], $data['external_id'])) {
                    $skipped++;

                    continue;
                }

                Transaction::create($data);
                $imported++;
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => []];
    }

    /**
     * @return array{0: list<array<string, string>>, 1: list<string>}
     */
    private function readRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('CSVファイルを開けませんでした。');
        }

        $headers = fgetcsv($handle);
        if ($headers === false || $headers === [null]) {
            fclose($handle);

            return [[], ['CSVヘッダー行が見つかりません。']];
        }

        $isBinanceJapanTradeExport = $this->isBinanceJapanTradeExport($headers);
        $isBitFlyerTradeExport = $this->isBitFlyerTradeExport($headers);
        $isBitbankSpotTradeExport = $this->isBitbankSpotTradeExport($headers);
        $isCoincheckIndustryStandardExport = $this->isCoincheckIndustryStandardExport($headers);
        $headers = array_map(
            fn ($header) => $this->normalizeHeader(
                (string) $header,
                $isBinanceJapanTradeExport,
                $isBitFlyerTradeExport,
                $isBitbankSpotTradeExport,
                $isCoincheckIndustryStandardExport,
            ),
            $headers,
        );
        $rows = [];
        $errors = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;

            if ($this->isBlankRow($values)) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = self::MAX_ROWS.'件を超えるCSVは分割して取り込んでください。';
                break;
            }

            if (count($values) !== count($headers)) {
                $errors[] = "{$line}行目: ヘッダー数と列数が一致しません。";

                continue;
            }

            $row = array_combine($headers, array_map(fn ($value) => trim((string) $value), $values));
            if ($isBinanceJapanTradeExport) {
                $row = $this->normalizeBinanceJapanTradeRow($row);
            }
            if ($isBitFlyerTradeExport) {
                $row = $this->normalizeBitFlyerTradeRow($row);
            }
            if ($isBitbankSpotTradeExport) {
                $row = $this->normalizeBitbankSpotTradeRow($row);
            }
            if ($isCoincheckIndustryStandardExport) {
                $row = $this->normalizeCoincheckIndustryStandardRow($row);
            }

            $rows[] = $row;
        }

        fclose($handle);

        if ($rows === [] && $errors === []) {
            $errors[] = '取り込める行がありません。';
        }

        return [$rows, $errors];
    }

    /**
     * @return array{portfolios: array<string, Portfolio>, assets: array<string, Asset>, exchanges: array<string, Exchange>}
     */
    private function context(User $user): array
    {
        $portfolios = [];
        foreach ($user->portfolios()->get(['id', 'name', 'user_id']) as $portfolio) {
            $portfolios[(string) $portfolio->id] = $portfolio;
            $portfolios[Str::lower($portfolio->name)] = $portfolio;
        }

        $assets = [];
        foreach (Asset::query()->get(['id', 'symbol', 'name']) as $asset) {
            $assets[Str::upper($asset->symbol)] = $asset;
        }

        $exchanges = [];
        foreach (Exchange::query()->get(['id', 'name', 'code']) as $exchange) {
            $exchanges[Str::lower($exchange->name)] = $exchange;
            $exchanges[Str::lower($exchange->code)] = $exchange;
        }

        return compact('portfolios', 'assets', 'exchanges');
    }

    /**
     * @param  array{portfolios: array<string, Portfolio>, assets: array<string, Asset>, exchanges: array<string, Exchange>}  $context
     * @param  array<string, string>  $row
     * @return array{data: array<string, mixed>, preview: array<string, mixed>, will_create_asset: bool}|array{error: string}
     */
    private function prepareRow(array $row, int $line, array &$context, ?int $defaultPortfolioId, bool $createMissingAsset): array
    {
        $portfolioValue = $this->value($row, ['portfolio_id', 'portfolio']);
        $portfolio = $this->portfolio($context, $portfolioValue, $defaultPortfolioId);
        if (! $portfolio) {
            return ['error' => "{$line}行目: ポートフォリオが見つかりません。CSVにポートフォリオ列を入れるか、既定のポートフォリオを選択してください。"];
        }

        $symbol = Str::upper($this->value($row, ['symbol', 'asset_symbol', 'asset_currency']));
        if ($symbol === '') {
            $symbol = $this->symbolFromMarketPair($this->value($row, ['market_pair']));
        }
        if ($symbol === '') {
            return ['error' => "{$line}行目: 銘柄シンボルが未入力です。"];
        }

        $asset = $context['assets'][$symbol] ?? null;
        $willCreateAsset = $asset === null;
        if (! $asset && $createMissingAsset) {
            $asset = Asset::create([
                'symbol' => $symbol,
                'name' => $this->value($row, ['asset_name']) ?: $symbol,
                'coingecko_id' => null,
                'icon_url' => null,
            ]);
            $context['assets'][$symbol] = $asset;
        }

        $type = $this->type($this->value($row, ['type', 'type_code']));
        if (! $type) {
            return ['error' => "{$line}行目: 種別は buy / sell / transfer_in / transfer_out のいずれかを指定してください。"];
        }

        $amount = $this->decimal($this->value($row, ['amount']));
        $price = $this->decimal($this->value($row, ['price_jpy', 'unit_price_jpy']));
        $fee = $this->decimal($this->value($row, ['fee_jpy']), true);
        if ($amount === null || $amount <= 0) {
            return ['error' => "{$line}行目: 数量は0より大きい数値を指定してください。"];
        }
        if ($price === null || $price < 0) {
            return ['error' => "{$line}行目: 単価(JPY)は0以上の数値を指定してください。"];
        }
        if ($fee === null || $fee < 0) {
            return ['error' => "{$line}行目: 手数料(JPY)は0以上の数値を指定してください。"];
        }

        $executedAt = $this->dateTime($this->value($row, ['executed_at']));
        if (! $executedAt) {
            return ['error' => "{$line}行目: 取引日時を日付として解釈できません。"];
        }
        if ($executedAt->isFuture()) {
            return ['error' => "{$line}行目: 取引日時は現在以前を指定してください。"];
        }

        $exchange = $this->exchange($context, $this->value($row, ['exchange_id', 'exchange']));
        $note = $this->value($row, ['note']);
        $feeRaw = $this->value($row, ['fee_raw']);
        if ($feeRaw !== '' && $this->feeUnit($feeRaw) !== 'JPY') {
            $note = trim($note.($note !== '' ? ' / ' : '').'手数料: '.$feeRaw);
        }
        $externalId = $this->value($row, ['external_id'])
            ?: hash('sha256', implode('|', [
                $portfolio->id,
                $asset?->id ?? $symbol,
                $exchange?->id ?? '',
                $type,
                number_format($amount, 8, '.', ''),
                number_format($price, 8, '.', ''),
                number_format($fee, 8, '.', ''),
                $executedAt->toDateTimeString(),
                $note,
            ]));

        return ['data' => [
            'portfolio_id' => $portfolio->id,
            'asset_id' => $asset?->id,
            'exchange_id' => $exchange?->id,
            'type' => $type,
            'amount' => $amount,
            'price_jpy' => $price,
            'fee_jpy' => $fee,
            'executed_at' => $executedAt,
            'note' => $note !== '' ? $note : null,
            'external_source' => 'csv:manual',
            'external_id' => $externalId,
            'synced_at' => now(),
        ], 'preview' => [
            'line' => $line,
            'executed_at' => $executedAt->format('Y-m-d H:i:s'),
            'type' => $type,
            'symbol' => $symbol,
            'amount' => $amount,
            'price_jpy' => $price,
            'fee_jpy' => $fee,
            'portfolio' => $portfolio->name,
            'exchange' => $exchange?->name,
            'note' => $note,
            'will_create_asset' => $willCreateAsset,
        ], 'will_create_asset' => $willCreateAsset];
    }

    private function normalizeHeader(
        string $header,
        bool $isBinanceJapanTradeExport = false,
        bool $isBitFlyerTradeExport = false,
        bool $isBitbankSpotTradeExport = false,
        bool $isCoincheckIndustryStandardExport = false,
    ): string {
        $key = $this->normalizedHeaderKey($header);

        if ($isBinanceJapanTradeExport) {
            return [
                'date(utc)' => 'executed_at',
                'date' => 'executed_at',
                'time' => 'executed_at',
                'pair' => 'market_pair',
                'market' => 'market_pair',
                'side' => 'type',
                'price' => 'price_jpy',
                'executed' => 'amount',
                'filled' => 'amount',
                'amount' => 'quote_amount_jpy',
                'fee' => 'fee_raw',
                'role' => 'role',
            ][$key] ?? str_replace([' ', '-'], '_', $key);
        }

        if ($isBitFlyerTradeExport) {
            return [
                '取引日時' => 'executed_at',
                '日時' => 'executed_at',
                '通貨' => 'market_pair',
                '通貨ペア' => 'market_pair',
                'マーケット' => 'market_pair',
                '取引種別' => 'type',
                '売買' => 'type',
                '取引価格' => 'price_jpy',
                '価格' => 'price_jpy',
                '通貨1' => 'asset_currency',
                '通貨1数量' => 'amount',
                '数量' => 'amount',
                '手数料' => 'fee_raw',
                '通貨1の対円レート' => 'asset_rate_jpy',
                '通貨2' => 'quote_currency',
                '通貨2数量' => 'quote_amount_jpy',
                '通貨2の対円レート' => 'quote_rate_jpy',
                '取引id' => 'external_id',
                '取引ID' => 'external_id',
                '注文id' => 'external_id',
                '注文ID' => 'external_id',
            ][$key] ?? str_replace([' ', '-'], '_', $key);
        }

        if ($isBitbankSpotTradeExport) {
            return [
                '注文id' => 'order_id',
                '注文ID' => 'order_id',
                '取引id' => 'trade_id',
                '取引ID' => 'trade_id',
                '通貨ペア' => 'market_pair',
                'ペア' => 'market_pair',
                '現物/信用' => 'market_type',
                'タイプ' => 'order_type',
                '売/買' => 'type',
                '売買' => 'type',
                '数量' => 'amount',
                '価格' => 'price_jpy',
                '実現損益' => 'realized_profit',
                '発生手数料' => 'fee_raw',
                '実現手数料' => 'realized_fee',
                '実現利息' => 'realized_interest',
                'm/t' => 'maker_taker',
                '取引日時' => 'executed_at',
                '日時' => 'executed_at',
            ][$key] ?? str_replace([' ', '-'], '_', $key);
        }

        if ($isCoincheckIndustryStandardExport) {
            return [
                '取引日時' => 'executed_at',
                '日時' => 'executed_at',
                '取引種別' => 'transaction_category',
                '取引形態' => 'transaction_mode',
                '通貨ペア' => 'market_pair',
                '増加通貨名' => 'increase_currency',
                '増加数量' => 'increase_amount',
                '減少通貨名' => 'decrease_currency',
                '減少数量' => 'decrease_amount',
                '約定代金' => 'quote_amount_jpy',
                '約定価格' => 'price_jpy',
                '約定価格/数量' => 'quote_amount_jpy',
                '単価' => 'price_jpy',
                '手数料通貨' => 'fee_currency',
                '手数料数量' => 'fee_amount',
                '登録番号' => 'external_id',
                '備考' => 'note',
            ][$key] ?? str_replace([' ', '-'], '_', $key);
        }

        return [
            '取引日時' => 'executed_at',
            '日時' => 'executed_at',
            '種別' => 'type',
            '種別コード' => 'type_code',
            '銘柄シンボル' => 'symbol',
            '銘柄' => 'symbol',
            '銘柄名' => 'asset_name',
            '数量' => 'amount',
            '単価(jpy)' => 'price_jpy',
            '単価（jpy）' => 'price_jpy',
            '取引単価' => 'price_jpy',
            '手数料(jpy)' => 'fee_jpy',
            '手数料（jpy）' => 'fee_jpy',
            '取引所' => 'exchange',
            'ポートフォリオ' => 'portfolio',
            'メモ' => 'note',
        ][$key] ?? str_replace([' ', '-'], '_', $key);
    }

    private function normalizedHeaderKey(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header));

        return Str::lower((string) $header);
    }

    /**
     * @param  list<mixed>  $headers
     */
    private function isBinanceJapanTradeExport(array $headers): bool
    {
        $keys = array_map(
            fn ($header): string => Str::lower((string) preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header))),
            $headers,
        );

        return count(array_intersect(['date(utc)', 'pair', 'side', 'price', 'executed', 'amount', 'fee'], $keys)) >= 5;
    }

    /**
     * @param  list<mixed>  $headers
     */
    private function isBitFlyerTradeExport(array $headers): bool
    {
        $keys = array_map(fn ($header): string => $this->normalizedHeaderKey((string) $header), $headers);

        return count(array_intersect(['取引日時', '通貨', '取引種別', '取引価格', '通貨1', '通貨1数量', '手数料'], $keys)) >= 5;
    }

    /**
     * @param  list<mixed>  $headers
     */
    private function isBitbankSpotTradeExport(array $headers): bool
    {
        $keys = array_map(fn ($header): string => $this->normalizedHeaderKey((string) $header), $headers);

        return count(array_intersect(['注文id', '取引id', '通貨ペア', '売/買', '数量', '価格', '発生手数料', '取引日時'], $keys)) >= 6;
    }

    /**
     * @param  list<mixed>  $headers
     */
    private function isCoincheckIndustryStandardExport(array $headers): bool
    {
        $keys = array_map(fn ($header): string => $this->normalizedHeaderKey((string) $header), $headers);

        return count(array_intersect(['取引日時', '取引種別', '取引形態', '通貨ペア', '増加通貨名', '増加数量', '減少通貨名', '減少数量', '約定価格', '手数料通貨', '手数料数量'], $keys)) >= 8;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeBinanceJapanTradeRow(array $row): array
    {
        $row['exchange'] = $row['exchange'] ?? 'Binance Japan';

        if (($row['fee_jpy'] ?? '') === '' && ($row['fee_raw'] ?? '') !== '' && $this->feeUnit($row['fee_raw']) === 'JPY') {
            $row['fee_jpy'] = $row['fee_raw'];
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeBitFlyerTradeRow(array $row): array
    {
        $row['exchange'] = $row['exchange'] ?? 'bitFlyer';

        if (($row['asset_currency'] ?? '') === '' && ($row['market_pair'] ?? '') !== '') {
            $row['asset_currency'] = $this->symbolFromMarketPair($row['market_pair']);
        }

        if (($row['fee_jpy'] ?? '') === '' && ($row['fee_raw'] ?? '') !== '' && $this->feeUnit($row['fee_raw']) === 'JPY') {
            $row['fee_jpy'] = $row['fee_raw'];
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeBitbankSpotTradeRow(array $row): array
    {
        $row['exchange'] = $row['exchange'] ?? 'bitbank';
        $row['external_id'] = $row['trade_id'] ?? $row['order_id'] ?? '';

        if (($row['fee_jpy'] ?? '') === '' && ($row['fee_raw'] ?? '') !== '') {
            $row['fee_jpy'] = $this->absoluteDecimalString($row['fee_raw']);
        }

        return $row;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeCoincheckIndustryStandardRow(array $row): array
    {
        $row['exchange'] = $row['exchange'] ?? 'Coincheck';

        $increaseCurrency = Str::upper($row['increase_currency'] ?? '');
        $decreaseCurrency = Str::upper($row['decrease_currency'] ?? '');

        if (($row['type'] ?? '') === '') {
            if ($increaseCurrency !== '' && $increaseCurrency !== 'JPY' && $decreaseCurrency === 'JPY') {
                $row['type'] = 'buy';
            } elseif ($increaseCurrency === 'JPY' && $decreaseCurrency !== '' && $decreaseCurrency !== 'JPY') {
                $row['type'] = 'sell';
            }
        }

        if (($row['symbol'] ?? '') === '') {
            $row['symbol'] = match (true) {
                $increaseCurrency !== '' && $increaseCurrency !== 'JPY' => $increaseCurrency,
                $decreaseCurrency !== '' && $decreaseCurrency !== 'JPY' => $decreaseCurrency,
                default => $this->symbolFromMarketPair($row['market_pair'] ?? ''),
            };
        }

        if (($row['amount'] ?? '') === '') {
            $row['amount'] = match ($row['type'] ?? '') {
                'buy' => $row['increase_amount'] ?? '',
                'sell' => $row['decrease_amount'] ?? '',
                default => '',
            };
        }

        if (($row['fee_jpy'] ?? '') === '' && ($row['fee_amount'] ?? '') !== '') {
            $feeCurrency = Str::upper($row['fee_currency'] ?? 'JPY');
            if ($feeCurrency === 'JPY') {
                $row['fee_jpy'] = $this->absoluteDecimalString($row['fee_amount']);
            } else {
                $row['fee_raw'] = trim($row['fee_amount'].' '.$feeCurrency);
            }
        }

        return $row;
    }

    private function symbolFromMarketPair(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $pair = Str::upper(str_replace(['-', '_', '/', ' '], '', $value));

        foreach (['JPY', 'USDT', 'USD', 'BTC', 'ETH', 'BNB'] as $quote) {
            if (str_ends_with($pair, $quote) && strlen($pair) > strlen($quote)) {
                return substr($pair, 0, -strlen($quote));
            }
        }

        return $pair;
    }

    private function feeUnit(string $value): string
    {
        $parts = preg_split('/\s+/', trim($value));

        return Str::upper((string) ($parts[1] ?? 'JPY'));
    }

    private function absoluteDecimalString(string $value): string
    {
        $decimal = $this->decimal($value, true);

        return $decimal === null ? '' : (string) abs($decimal);
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $keys
     */
    private function value(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return trim($row[$key]);
            }
        }

        return '';
    }

    private function portfolio(array $context, string $value, ?int $defaultPortfolioId): ?Portfolio
    {
        if ($value !== '') {
            return $context['portfolios'][$value]
                ?? $context['portfolios'][Str::lower($value)]
                ?? null;
        }

        return $defaultPortfolioId !== null
            ? ($context['portfolios'][(string) $defaultPortfolioId] ?? null)
            : null;
    }

    private function exchange(array $context, string $value): ?Exchange
    {
        if ($value === '') {
            return null;
        }

        return $context['exchanges'][$value]
            ?? $context['exchanges'][Str::lower($value)]
            ?? null;
    }

    private function type(string $value): ?string
    {
        $key = Str::lower($value);

        return [
            'buy' => Transaction::TYPE_BUY,
            '買' => Transaction::TYPE_BUY,
            '買い' => Transaction::TYPE_BUY,
            '購入' => Transaction::TYPE_BUY,
            'sell' => Transaction::TYPE_SELL,
            '売' => Transaction::TYPE_SELL,
            '売り' => Transaction::TYPE_SELL,
            '売却' => Transaction::TYPE_SELL,
            'transfer_in' => Transaction::TYPE_TRANSFER_IN,
            '入庫' => Transaction::TYPE_TRANSFER_IN,
            '入金' => Transaction::TYPE_TRANSFER_IN,
            'transfer_out' => Transaction::TYPE_TRANSFER_OUT,
            '出庫' => Transaction::TYPE_TRANSFER_OUT,
            '出金' => Transaction::TYPE_TRANSFER_OUT,
        ][$key] ?? null;
    }

    private function decimal(string $value, bool $nullableZero = false): ?float
    {
        if ($value === '') {
            return $nullableZero ? 0.0 : null;
        }

        $normalized = trim(str_replace([',', '¥', '￥', '円'], '', $value));
        $normalized = preg_replace('/\s*[A-Za-z]+$/', '', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function dateTime(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function duplicateExists(string $externalSource, string $externalId): bool
    {
        return Transaction::query()
            ->where('external_source', $externalSource)
            ->where('external_id', $externalId)
            ->exists();
    }

    /**
     * @param  list<mixed>  $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
