# bitbank 約定履歴同期

bitbank の Private REST API から約定履歴を取得し、アプリの取引履歴へ取り込みます。

## API キー

bitbank 側で API キーを作成します。

- 読み取りに必要な権限だけを付与してください
- 売買・出金などの更新権限は付けないでください
- API Secret は保存時に暗号化されます
- `.env` や Git には API キーを保存しません

bitbank API には bitFlyer の `/v1/me/getpermissions` のような権限一覧APIがないため、アプリは登録時に読み取りAPIが呼べることだけを確認します。権限を最小化する運用を必ず徹底してください。

## 接続登録

デフォルトでは bitbank の `/v1/spot/pairs` から取得できる有効な JPY 建て現物ペアを同期対象にします。

```bash
php artisan bitbank:connect demo@example.com 2
```

デフォルトでは登録日当日以降の約定だけを同期します。過去分も含める場合:

```bash
php artisan bitbank:connect demo@example.com 2 --sync-start-date=all
```

日付を指定する場合:

```bash
php artisan bitbank:connect demo@example.com 2 --sync-start-date=2026-01-01
```

対話入力を避ける場合:

```bash
php artisan bitbank:connect demo@example.com 2 --key=YOUR_KEY --secret=YOUR_SECRET
```

`btc_jpy` だけに絞る場合:

```bash
php artisan bitbank:connect demo@example.com 2 --pair=btc_jpy
```

## 同期

```bash
php artisan bitbank:sync-executions
```

特定接続だけ同期:

```bash
php artisan bitbank:sync-executions --connection=1
```

管理画面の「連携」からも手動同期できます。

## 現在の対応範囲

- Public API `/v1/spot/pairs` で返る有効な JPY 建て現物ペア
- Private API `/v1/user/spot/trade_history`
- `buy` / `sell` の取引取り込み
- bitbank `trade_id` とペア名による重複取り込み防止

BTC 建てペアは、約定価格が JPY ではないため対象外です。取り込む場合は、約定時点の JPY 換算レートを別途扱う必要があります。
