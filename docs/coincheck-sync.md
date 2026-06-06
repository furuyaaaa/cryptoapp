# Coincheck 取引履歴同期

Coincheck の Private API から取引所の取引履歴を取得し、アプリの取引履歴へ取り込みます。

## API キー

Coincheck 側で API キーを作成します。

- 読み取りに必要な権限だけを付与してください
- 売買・送金などの更新権限は付けないでください
- API Secret は保存時に暗号化されます
- `.env` や Git には API キーを保存しません

Coincheck API ではキーごとに機能権限を設定できます。アプリは登録時に残高取得APIが呼べることを確認します。

## 接続登録

デフォルトでは Coincheck の取引所APIで利用可能な JPY 建てペアを同期対象にします。

```bash
php artisan coincheck:connect demo@example.com 2
```

デフォルトでは登録日当日以降の取引だけを同期します。過去分も含める場合:

```bash
php artisan coincheck:connect demo@example.com 2 --sync-start-date=all
```

日付を指定する場合:

```bash
php artisan coincheck:connect demo@example.com 2 --sync-start-date=2026-01-01
```

対話入力を避ける場合:

```bash
php artisan coincheck:connect demo@example.com 2 --key=YOUR_KEY --secret=YOUR_SECRET
```

`btc_jpy` だけに絞る場合:

```bash
php artisan coincheck:connect demo@example.com 2 --pair=btc_jpy
```

## 同期

```bash
php artisan coincheck:sync-executions
```

特定接続だけ同期:

```bash
php artisan coincheck:sync-executions --connection=1
```

管理画面の「連携」からも手動同期できます。

## 現在の対応範囲

- Private API `/api/exchange/orders/transactions_pagination`
- 公式ドキュメントに記載された JPY 建て取引所ペア
- `buy` / `sell` の取引取り込み
- Coincheck 取引IDとペア名による重複取り込み防止

JPY 建て以外のペアは、約定価格が JPY ではないため対象外です。取り込む場合は、約定時点の JPY 換算レートを別途扱う必要があります。
