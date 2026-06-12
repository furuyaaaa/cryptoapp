# Coinbase Advanced Trade API 同期

Coinbase Advanced Trade API の約定履歴を `transactions` に取り込む運用手順です。

## 対象

- Advanced Trade API の `GET /api/v3/brokerage/orders/historical/fills`
- USD / USDC / USDT 建ての現物商品
- API Key の `view` 権限で取得できる履歴

USD / USDC 建ての約定も、海外取引所連携の暫定方針に合わせて取引日の USDT/JPY 日次レートで JPY 換算します。完全な法定通貨為替レートが必要な場合は、別途 USD/JPY レートソースを追加してください。

## API キー

Coinbase Developer Platform で Coinbase App / Advanced Trade 用の API キーを作成します。

- API Key 欄: `organizations/{org_id}/apiKeys/{key_id}` 形式の Key name
- API Secret 欄: `-----BEGIN EC PRIVATE KEY-----` で始まる EC 秘密鍵PEM
- 必要権限: `view`

売買・送金・出金などの更新系権限は不要です。

## CLI

```bash
php artisan coinbase:connect demo@example.com <portfolio_id>
php artisan coinbase:sync-executions
```

特定の接続だけ同期する場合:

```bash
php artisan coinbase:sync-executions --connection=<exchange_connection_id>
```

## 注意点

- API登録時は `accounts` の読み取り疎通を確認します。
- Convert、Earn、ステーキング、入出庫など、Advanced Trade の fills で取得できない履歴は手動登録またはCSVインポートで補完してください。
- Coinbase側の提供国・利用可否はユーザーの居住国とアカウント状態に依存します。運用前にCoinbase側で利用可能なことを確認してください。
