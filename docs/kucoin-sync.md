# KuCoin 約定履歴同期

KuCoin の Spot API から USDT 建て現物の約定履歴を取得し、取引履歴へ取り込みます。

## 対象

- 対応: Spot の USDT 建てペア（例: `BTC-USDT`）
- 未対応: Futures、Margin、Earn、Convert、USDT 以外の建て通貨
- API取得範囲: KuCoin API の仕様に合わせて7日ごとの期間に分割して取得

APIで取得できない履歴や USDT 以外の建て通貨は、CSVインポートまたは手動登録で補完してください。

## APIキー

KuCoin 側で読み取り用途の API Key / API Secret / API Passphrase を作成します。売買、送金、出金などの更新系権限は不要です。

アプリでは以下のヘッダー署名で読み取りAPIを呼び出します。

- `KC-API-KEY`
- `KC-API-SIGN`
- `KC-API-TIMESTAMP`
- `KC-API-PASSPHRASE`
- `KC-API-KEY-VERSION`

登録時は `/api/v1/user/api-key` で読み取り疎通を確認します。

## JPY換算

KuCoin の約定価格は `BTC-USDT` など USDT 建てで返るため、取引日の USDT/JPY レートを使って `price_jpy` に換算します。

- レート取得元: CoinGecko の Tether historical price
- 保存先: `daily_quote_rates`
- 一意キー: `base_currency`, `quote_currency`, `rate_date`

手数料が `USDT` の場合は同じ日次レートで `fee_jpy` に換算します。手数料が `BTC` や `KCS` など USDT 以外の場合は、JPY換算せず取引メモに `手数料: 0.00001 BTC` のように残します。

## CLI

```bash
php artisan kucoin:connect demo@example.com <portfolio_id>
php artisan kucoin:sync-executions
```

特定シンボルのみ登録する場合:

```bash
php artisan kucoin:connect demo@example.com <portfolio_id> --symbol=BTC-USDT
```

同期開始日は `today`, `all`, `YYYY-MM-DD` を指定できます。

```bash
php artisan kucoin:connect demo@example.com <portfolio_id> --sync-start-date=2026-01-01
```

## 定期同期

スケジューラで30分ごとに同期します。

```bash
php artisan schedule:work
```

## 今後の確認事項

- 実アカウントのレスポンスで、`feeCurrency` の通貨・符号・欠損パターンを確認する
- KuCoin Web から出力するCSVの列名を確認し、API対象外履歴の補完取り込み方針を整理する
- USDT 以外の建て通貨を対象にする場合は、`daily_quote_rates` の対応通貨を増やす
