# Bitget 約定履歴同期

Bitget の Spot API から USDT 建て現物の約定履歴を取得し、取引履歴へ取り込みます。

## 対象

- 対応: Spot の USDT 建てペア（例: `BTCUSDT`）
- 未対応: Futures、Margin、Copy Trading、Earn、Convert、USDT 以外の建て通貨
- API取得範囲: Bitget API の仕様により直近90日以内

90日より古い履歴は、Bitget Web からダウンロードしたCSVを整形してCSVインポートするか、手動登録で補完してください。

## APIキー

Bitget 側で読み取り用途の API Key / API Secret / API Passphrase を作成します。売買、送金、出金などの更新系権限は不要です。

アプリでは以下のヘッダー署名で読み取りAPIを呼び出します。

- `ACCESS-KEY`
- `ACCESS-SIGN`
- `ACCESS-PASSPHRASE`
- `ACCESS-TIMESTAMP`

登録時は `/api/v2/spot/account/assets` で読み取り疎通を確認します。

## JPY換算

Bitget の約定価格は `BTCUSDT` など USDT 建てで返るため、取引日の USDT/JPY レートを使って `price_jpy` に換算します。

- レート取得元: CoinGecko の Tether historical price
- 保存先: `daily_quote_rates`
- 一意キー: `base_currency`, `quote_currency`, `rate_date`
- 例: `USDT/JPY` の `2026-01-01` レート

手数料が `USDT` の場合は同じ日次レートで `fee_jpy` に換算します。手数料が `BTC` や `BGB` など USDT 以外の場合は、JPY換算せず取引メモに `手数料: 0.00001 BTC` のように残します。

## CLI

```bash
php artisan bitget:connect demo@example.com <portfolio_id>
php artisan bitget:sync-executions
```

特定シンボルのみ登録する場合:

```bash
php artisan bitget:connect demo@example.com <portfolio_id> --symbol=BTCUSDT
```

同期開始日は `today`, `all`, `YYYY-MM-DD` を指定できます。

```bash
php artisan bitget:connect demo@example.com <portfolio_id> --sync-start-date=2026-01-01
```

`all` を指定しても Bitget API の取得範囲は直近90日以内です。

## 定期同期

スケジューラで30分ごとに同期します。

```bash
php artisan schedule:work
```

## 今後の確認事項

- 日本居住者がBitgetを継続利用できるか、運用前に最新の規約・規制状況を確認する
- 実アカウントのレスポンスで、`feeDetail` の通貨・符号・欠損パターンを確認する
- Bitget Web から出力するCSVの列名を確認し、90日超の補完取り込みテストを追加する
