# Binance Japan 約定履歴同期

Binance Japan の API Key / API Secret を登録し、Binance Spot API の `myTrades` から JPY 建て現物の約定履歴を取り込みます。

## 対象

- Binance Spot API で取得できる JPY 建て現物シンボル
- `ALL_JPY_SYMBOLS` を指定した場合は、`exchangeInfo` で `quoteAsset=JPY` かつ Spot 取引可能なシンボルを同期
- 個別指定は `BTCJPY` など、JPY 建て現物シンボルのみ対応

## APIキー

Binance 側で API Key / API Secret を発行し、読み取りに必要な最小権限だけを付与してください。

このアプリでは Binance のログインIDやパスワードは扱いません。売買、出金、注文取消などの更新系権限は不要です。

## 登録

管理画面の「連携」から Binance Japan を選択して登録できます。

CLI で登録する場合:

```bash
php artisan binance:connect demo@example.com <portfolio_id>
```

オプション:

```bash
php artisan binance:connect demo@example.com <portfolio_id> --symbol=BTCJPY
php artisan binance:connect demo@example.com <portfolio_id> --sync-start-date=2026-01-01
php artisan binance:connect demo@example.com <portfolio_id> --sync-start-date=all
```

## 同期

```bash
php artisan binance:sync-executions
```

特定の連携だけ同期する場合:

```bash
php artisan binance:sync-executions --connection=<exchange_connection_id>
```

定期同期は Laravel scheduler から30分ごとに実行されます。

```bash
php artisan schedule:work
```

## 取り込み仕様

- Binance の `myTrades` は `symbol` 必須のため、全銘柄同期では対象シンボルごとに履歴を取得します。
- 同期開始日が指定されている場合、24時間以内の時間窓に分けて取得します。
- `sync-start-date=all` の場合は、Binance API が返す直近履歴のみを取り込みます。完全な過去分バックフィルが必要な場合は CSV インポートなど別経路で補完してください。
- 手数料は `commissionAsset=JPY` の場合のみ `fee_jpy` に反映します。BNB や暗号資産建て手数料の JPY 換算は今後の拡張対象です。
- Convert、販売所、Earn など Spot API の `myTrades` に出ない履歴は対象外です。必要に応じて取引履歴画面から手動で追加してください。
