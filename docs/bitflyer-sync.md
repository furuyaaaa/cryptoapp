# bitFlyer Sync

bitFlyer の約定履歴を `transactions` に取り込むローカル運用手順です。

## API キー

bitFlyer 側では読み取り専用の API キーを作成してください。

必要な権限:

- `/v1/me/getpermissions`
- `/v1/me/getexecutions`

発注、取消、出金系の権限が付いているキーは `bitflyer:connect` が拒否します。

## 接続登録

同期先のポートフォリオ ID を確認してから登録します。デフォルトでは bitFlyer の Market List から取得できるすべての JPY 建て Spot 商品を同期対象にします。

```bash
php artisan bitflyer:connect demo@example.com 2
```

デフォルトでは登録日当日以降の約定だけを同期します。過去分も含める場合:

```bash
php artisan bitflyer:connect demo@example.com 2 --sync-start-date=all
```

日付を指定する場合:

```bash
php artisan bitflyer:connect demo@example.com 2 --sync-start-date=2026-01-01
```

非対話で登録する場合:

```bash
php artisan bitflyer:connect demo@example.com 2 --key=YOUR_KEY --secret=YOUR_SECRET
```

BTC_JPY だけに絞る場合:

```bash
php artisan bitflyer:connect demo@example.com 2 --product=BTC_JPY
```

`YOUR_KEY` / `YOUR_SECRET` は DB 上で暗号化保存されます。`.env` や Git には保存しません。

## 同期

```bash
php artisan bitflyer:sync-executions
```

特定の接続だけ同期する場合:

```bash
php artisan bitflyer:sync-executions --connection=1
```

スケジューラでは 30 分ごとに実行されます。

## 現在の対応範囲

- Market List API (`/v1/markets`) で返る JPY 建て Spot 商品
- Private API `/v1/me/getexecutions`
- `BUY` / `SELL` の取引取り込み
- bitFlyer 約定 ID による重複取り込み防止

BTC 建て商品 (`ETH_BTC`, `BCH_BTC` など) は、約定価格が JPY ではないため対象外です。取り込む場合は、約定時点の JPY 換算レートを別途扱う必要があります。
