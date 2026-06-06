# GMOコイン 約定履歴同期

GMOコインの Private API から最新約定一覧を取得し、アプリの取引履歴へ取り込みます。

## API キー

GMOコイン側で API キーを作成します。

- 読み取りに必要な権限だけを付与してください
- 売買、出金などの更新権限は付けないでください
- API Secret は保存時に暗号化されます
- `.env` や Git には API キーを保存しません

GMOコイン API ではキーごとの権限一覧を取得できないため、アプリは登録時に資産残高 API が呼べることだけを確認します。

## 接続登録

デフォルトでは、アプリ側で対応している GMOコインの現物銘柄を同期対象にします。

```bash
php artisan gmo-coin:connect demo@example.com 2
```

デフォルトでは登録日当日以降の約定だけを同期します。過去分も含める場合:

```bash
php artisan gmo-coin:connect demo@example.com 2 --sync-start-date=all
```

日付を指定する場合:

```bash
php artisan gmo-coin:connect demo@example.com 2 --sync-start-date=2026-01-01
```

対話入力を避ける場合:

```bash
php artisan gmo-coin:connect demo@example.com 2 --key=YOUR_KEY --secret=YOUR_SECRET
```

`BTC` だけに絞る場合:

```bash
php artisan gmo-coin:connect demo@example.com 2 --symbol=BTC
```

## 同期

```bash
php artisan gmo-coin:sync-executions
```

特定接続だけ同期:

```bash
php artisan gmo-coin:sync-executions --connection=1
```

管理画面の「連携」からも手動同期できます。

## 現在の対応範囲

- Private API `/v1/latestExecutions`
- 読み取り確認用の `/v1/account/assets`
- アプリ側で定義した GMOコイン現物銘柄
- `BUY` / `SELL` の取引取り込み
- 銘柄と GMOコイン約定IDによる重複取り込み防止

GMOコインの `/v1/latestExecutions` は最新約定一覧の API で、取得対象は直近の履歴に限られます。過去分をまとめて初回バックフィルする場合は、CSV インポートなど別経路の設計が必要です。
