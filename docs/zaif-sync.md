# Zaif 約定履歴同期

Zaif の現物取引APIから Orderbook Trading の取引履歴を取得し、アプリの取引履歴へ取り込みます。

## API キー

Zaif 側で API キーを作成します。

- `info` 権限だけを付与してください
- `trade`、`withdraw` などの更新権限は付けないでください
- API Secret は保存時に暗号化されます
- `.env` や Git には API キーを保存しません

アプリは登録時に `get_info2` を呼び、`info` 権限があることを確認します。

## 接続登録

デフォルトでは Zaif の公開APIで取得できる、トークンではない JPY 建て現物ペアを同期対象にします。

```bash
php artisan zaif:connect demo@example.com 2
```

デフォルトでは登録日当日以降の約定だけを同期します。過去分も含める場合:

```bash
php artisan zaif:connect demo@example.com 2 --sync-start-date=all
```

日付を指定する場合:

```bash
php artisan zaif:connect demo@example.com 2 --sync-start-date=2026-01-01
```

対話入力を避ける場合:

```bash
php artisan zaif:connect demo@example.com 2 --key=YOUR_KEY --secret=YOUR_SECRET
```

`btc_jpy` だけに絞る場合:

```bash
php artisan zaif:connect demo@example.com 2 --pair=btc_jpy
```

## 同期

```bash
php artisan zaif:sync-executions
```

特定接続だけ同期:

```bash
php artisan zaif:sync-executions --connection=1
```

管理画面の「連携」からも手動同期できます。

## 現在の対応範囲

- 現物取引API `/tapi` の `trade_history`
- 読み取り確認用の `get_info2`
- 公開API `/api/1/currency_pairs/all`
- `bid` / `ask` の取引取り込み
- Zaif 取引IDとペア名による重複取り込み防止

Zaif API は Orderbook Trading の履歴が対象です。かんたん売買など API で取得できない履歴は、取引履歴画面から手動で追加してください。
