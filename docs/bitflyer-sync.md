# bitFlyer Sync

bitFlyer の約定履歴を `transactions` に取り込むローカル運用手順です。

## API キー

bitFlyer 側では読み取り専用の API キーを作成してください。

必要な権限:

- `/v1/me/getpermissions`
- `/v1/me/getexecutions`

発注、取消、出金系の権限が付いているキーは `bitflyer:connect` が拒否します。

## 接続登録

同期先のポートフォリオ ID を確認してから登録します。

```bash
php artisan bitflyer:connect demo@example.com 2
```

非対話で登録する場合:

```bash
php artisan bitflyer:connect demo@example.com 2 --key=YOUR_KEY --secret=YOUR_SECRET
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

- `BTC_JPY`
- Private API `/v1/me/getexecutions`
- `BUY` / `SELL` の取引取り込み
- bitFlyer 約定 ID による重複取り込み防止

その他の商品コードは、JPY建てスポットから順に追加してください。
