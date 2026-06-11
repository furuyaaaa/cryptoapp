# Crypto Portfolio App

## 概要

複数の仮想通貨取引所に分散している資産を一元管理するアプリです。

- 通貨ごとの保有量
- 平均取得単価
- 現在価格
- 損益

を一画面で確認できます。

---

## ダッシュボード

![ダッシュボード（スクリーンショット）](docs/screenshots/スクリーンショット%202026-05-17%20183905.png)

---

## ローカル起動手順

1. **リポジトリを取得**

   ```bash
   git clone <このリポジトリの URL>
   cd cryptoapp
   ```

2. **PHP 依存関係**

   ```bash
   composer install
   ```

3. **フロントエンド依存関係**

   ```bash
   npm install
   ```

4. **環境変数**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   `.env` の **`DB_*`** を PostgreSQL に合わせて設定する（`.env.example` のコメント参照）。DB とユーザー `cryptoapp_app` をまだ作っていない場合は、PostgreSQL でロール・データベースを作成してから `migrate` してください。

5. **マイグレーション**

   ```bash
   php artisan migrate
   ```

   （任意）非本番でデモデータを入れる場合: `php artisan db:seed --class=DemoSeeder`

6. **開発サーバー（別ターミナルで実行）**

   ```bash
   npm run dev
   ```

   ```bash
   php artisan serve
   ```

7. ブラウザで `http://127.0.0.1:8000` を開く。

---

## デモ

未ログインで画面だけ確認する場合:

```text
http://127.0.0.1:8000/demo
```

デモユーザーでログインして操作する場合は、非本番環境でデモデータを投入します。

```bash
php artisan db:seed --class=DemoSeeder
```

ログイン情報:

- メールアドレス: `demo@example.com`
- パスワード: `password`

---

## 主な機能

- 取引履歴の登録（購入価格・数量）
- 通貨ごとの資産集計
- 現在価格の取得（API 連携）
- 損益の自動計算
- 取引所別の管理
- bitFlyer 約定履歴の同期（読み取り専用 API キー）
- bitbank / Coincheck / GMOコイン / Zaif / Binance Japan の約定履歴同期
- Bitget の USDT 建て現物約定履歴同期（USDT/JPY 日次レートでJPY換算）
- KuCoin の USDT 建て現物約定履歴同期（USDT/JPY 日次レートでJPY換算）

---

## 取引所 API 連携の仕組み

ユーザーが各取引所にログインし、取引所の設定画面で API Key / API Secret を発行します。その API Key / API Secret をこのアプリの「連携」画面に登録すると、アプリが取引所 API から売買済みの約定履歴を取得し、取引履歴へ反映します。

このアプリに取引所のログインIDやパスワードを入力する必要はありません。登録するのは、取引所が発行した API Key / API Secret だけです。

APIキーは必ず読み取り専用、または約定履歴・残高確認に必要な最小権限で作成してください。売買、注文取消、送金、出金などの更新権限は不要です。API Secret は保存時に暗号化され、`.env` や Git には保存しません。

連携登録時に、API同期で取り込む開始日を選択できます。

- 今日から: 新規利用開始後の売買だけを同期します
- 過去分も含める: 取引所APIで取得できる範囲を取り込みます
- 日付指定: 指定日以降の売買だけを同期します

取引所APIで取得できない古い履歴や、取り込み対象外の履歴は、取引履歴画面から手動で追加・編集・削除できます。

---

## 取引履歴 CSV インポート

APIで取得できない過去分、販売所、Convert、Earn などの履歴は、取引履歴画面の「Import」からCSVで取り込めます。CSVを選択すると、まず登録予定件数、重複スキップ件数、新規作成される銘柄数、先頭行の内容をプレビューできます。内容を確認してから「この内容で取り込む」を押すと登録されます。

対応列:

- `executed_at`: 取引日時
- `type`: `buy` / `sell` / `transfer_in` / `transfer_out`
- `symbol`: 銘柄シンボル
- `amount`: 数量
- `price_jpy`: 単価(JPY)
- `fee_jpy`: 手数料(JPY)
- `exchange`: 取引所名またはコード
- `portfolio`: ポートフォリオ名またはID
- `note`: メモ
- `external_id`: 取引所やCSV側の一意ID

取引履歴のCSVエクスポートで出力される日本語ヘッダーにも対応しています。CSVにポートフォリオ列がない場合は、インポート画面で既定のポートフォリオを選択してください。`external_id` が同じ行は重複としてスキップします。未登録の銘柄シンボルはプレビューで「新規」と表示され、確定時にCSVの銘柄名またはシンボル名で自動作成されます。

プレビュー用の一時CSVは `storage/app/private/transaction-import-previews` に保存され、取り込み確定時に削除されます。確定せず画面を離れたファイルは、日次スケジュールの `transaction-import-previews:prune` が24時間超過後に削除します。手動確認する場合は `php artisan transaction-import-previews:prune --dry-run`、保持時間を変える場合は `--hours=48` のように指定してください。

インポート画面では、標準CSVと対応済み取引所（Binance Japan / bitFlyer / bitbank / Coincheck / GMOコイン / Zaif）向けのテンプレートをダウンロードできます。取引所別テンプレートはこのアプリが取り込める標準列に取引所名とサンプル値を入れた補助ファイルです。取引所から出力した生CSVの列名が異なる場合は、テンプレートの列に合わせて整形してから取り込んでください。

Binance Japan の現物取引CSVは、`Date(UTC)`, `Pair`, `Side`, `Price`, `Executed`, `Amount`, `Fee` の列を含む形式であれば、そのままプレビューできます。手数料が BTC など JPY 以外の場合は JPY 換算せず、取引メモへ手数料情報として残します。

bitFlyer の取引履歴CSVは、`取引日時`, `通貨`, `取引種別`, `取引価格`, `通貨1`, `通貨1数量`, `手数料` の列を含む形式であれば、そのままプレビューできます。手数料が BTC など JPY 以外の場合は JPY 換算せず、取引メモへ手数料情報として残します。

bitbank の約定履歴CSVは、`注文id`, `取引id`, `通貨ペア`, `売/買`, `数量`, `価格`, `発生手数料`, `取引日時` の列を含む形式であれば、そのままプレビューできます。発生手数料がマイナス表記の場合は、手数料額として絶対値で取り込みます。

Coincheck の業界標準フォーマットCSVは、`取引日時`, `取引種別`, `取引形態`, `通貨ペア`, `増加通貨名`, `増加数量`, `減少通貨名`, `減少数量`, `約定価格`, `手数料通貨`, `手数料数量` の列を含む形式であれば、そのままプレビューできます。JPY が減少して暗号資産が増加する行は買い、暗号資産が減少して JPY が増加する行は売りとして判定します。

Zaif の取引履歴CSVは、`取引日時`, `通貨ペア`, `売買`, `価格`, `数量`, `手数料` の列を含む形式であれば、そのままプレビューできます。手数料が BTC など JPY 以外の場合は JPY 換算せず、取引メモへ手数料情報として残します。

---

## bitFlyer 連携

bitFlyer の読み取り専用 API キーを登録すると、bitFlyer の Market List API で取得できる JPY 建て Spot 商品の約定履歴を取引履歴へ取り込めます。

1. bitFlyer 側で読み取り専用 API キーを作成する
2. ログイン後、上部ナビの「連携」を開く
3. 同期先ポートフォリオ、API Key、API Secret を登録する
4. 「同期」を押して約定履歴を取り込む

CLI でも登録・同期できます。

```bash
php artisan bitflyer:connect demo@example.com <portfolio_id>
php artisan bitflyer:sync-executions
```

発注、取消、出金系の権限が付いた API キーは登録時に拒否されます。BTC 建て商品は JPY 換算が別途必要なため対象外です。詳しい運用手順は [docs/bitflyer-sync.md](docs/bitflyer-sync.md) を参照してください。

定期同期を使う場合はスケジューラを起動してください。

```bash
php artisan schedule:work
```

## bitbank 連携

bitbank の API キーを登録すると、bitbank の Pair List API で取得できる有効な JPY 建て現物ペアの約定履歴を取引履歴へ取り込めます。

```bash
php artisan bitbank:connect demo@example.com <portfolio_id>
php artisan bitbank:sync-executions
```

bitbank API では権限一覧を取得できないため、登録時は読み取りAPIの疎通だけを確認します。APIキーには売買・出金権限を付けないでください。詳しい運用手順は [docs/bitbank-sync.md](docs/bitbank-sync.md) を参照してください。

## Coincheck 連携

Coincheck の API キーを登録すると、Coincheck 取引所の JPY 建てペアの取引履歴を取引履歴へ取り込めます。

```bash
php artisan coincheck:connect demo@example.com <portfolio_id>
php artisan coincheck:sync-executions
```

APIキーには読み取りに必要な権限だけを付与し、売買・送金権限を付けないでください。詳しい運用手順は [docs/coincheck-sync.md](docs/coincheck-sync.md) を参照してください。

## GMOコイン 連携

GMOコインの API キーを登録すると、GMOコインの現物約定履歴を取引履歴へ取り込めます。

```bash
php artisan gmo-coin:connect demo@example.com <portfolio_id>
php artisan gmo-coin:sync-executions
```

APIキーには読み取りに必要な権限だけを付与し、売買・出金権限を付けないでください。GMOコインの最新約定 API は直近の履歴が対象のため、過去分の初回バックフィルは CSV インポートなど別経路で扱う必要があります。詳しい運用手順は [docs/gmo-coin-sync.md](docs/gmo-coin-sync.md) を参照してください。

TODO:

- GMOコインの取引履歴CSVについて、実ファイルの列名・売買判定・数量・単価・手数料・約定日時・一意IDのマッピングを確認し、プレビューと確定取り込みの自動テストを追加する。

## Zaif 連携

Zaif の API キーを登録すると、Zaif Orderbook Trading の JPY 建て現物約定履歴を取引履歴へ取り込めます。

```bash
php artisan zaif:connect demo@example.com <portfolio_id>
php artisan zaif:sync-executions
```

APIキーには `info` 権限だけを付与し、売買・出金権限を付けないでください。かんたん売買など API で取得できない履歴は、取引履歴画面から手動で追加してください。詳しい運用手順は [docs/zaif-sync.md](docs/zaif-sync.md) を参照してください。

TODO:

- Zaif の取引履歴CSVについて、実ファイルの列名・文字コード・日付形式・手数料表記の揺れを確認し、追加の自動テストを後日整備する。

## Binance Japan 連携

Binance Japan の API キーを登録すると、Binance Spot API で取得できる JPY 建て現物約定履歴を取引履歴へ取り込めます。

検証済みの操作フロー:

1. Binance Japan 側で API Key / API Secret を発行する
2. アプリにログインし、上部ナビの「連携」を開く
3. 取引所で `Binance Japan` を選択する
4. 商品コードは `すべてのJPY建て現物` または対象銘柄を選択する
5. 同期開始を `今日から`、`過去分も含める`、`日付指定` から選択する
6. API Key / API Secret を登録する
7. 連携一覧の「同期」を押し、取引履歴とダッシュボードへの反映を確認する

```bash
php artisan binance:connect demo@example.com <portfolio_id>
php artisan binance:sync-executions
```

APIキーには読み取りに必要な権限だけを付与し、売買・出金権限を付けないでください。`sync-start-date=all` の場合は API が返す直近履歴が対象です。完全な過去分バックフィルや Spot API で取得できない Convert・販売所・Earn などの履歴は、CSV インポートや手動登録で補完してください。詳しい運用手順は [docs/binance-sync.md](docs/binance-sync.md) を参照してください。

## Bitget 連携

Bitget の API キーを登録すると、Bitget Spot API で取得できる USDT 建て現物約定履歴を取引履歴へ取り込めます。

```bash
php artisan bitget:connect demo@example.com <portfolio_id>
php artisan bitget:sync-executions
```

Bitget は API Key / API Secret に加えて API Passphrase が必要です。APIキーには読み取りに必要な権限だけを付与し、売買・出金権限を付けないでください。

Bitget の約定価格は USDT 建てのため、取引日の USDT/JPY 日次レートを CoinGecko から取得し、`daily_quote_rates` に保存して JPY 換算します。Bitget API の約定履歴取得範囲は直近90日以内です。90日より古い履歴や、API で取得できない履歴は CSV インポートや手動登録で補完してください。詳しい運用手順は [docs/bitget-sync.md](docs/bitget-sync.md) を参照してください。

## KuCoin 連携

KuCoin の API キーを登録すると、KuCoin Spot API で取得できる USDT 建て現物約定履歴を取引履歴へ取り込めます。

```bash
php artisan kucoin:connect demo@example.com <portfolio_id>
php artisan kucoin:sync-executions
```

KuCoin は API Key / API Secret に加えて API Passphrase が必要です。APIキーには読み取りに必要な権限だけを付与し、売買・出金権限を付けないでください。

KuCoin の約定価格は USDT 建てのため、取引日の USDT/JPY 日次レートを CoinGecko から取得し、`daily_quote_rates` に保存して JPY 換算します。KuCoin API の約定履歴は7日ごとに分割して取得します。API で取得できない履歴や USDT 以外の建て通貨は、CSV インポートや手動登録で補完してください。詳しい運用手順は [docs/kucoin-sync.md](docs/kucoin-sync.md) を参照してください。

---

## 今後の対応予定・未対応取引所

取引所 API 連携は、公式 API で約定履歴を取得できること、読み取り専用または最小権限の API キーで運用できること、モックテストで同期処理を検証できることを前提に順次追加します。

現時点の対応状況:

- 対応済み: bitFlyer、bitbank、Coincheck、GMOコイン、Zaif、Binance Japan、Bitget、KuCoin
- 未対応: Binance グローバル、Coinbase、OKX、Gate.io
- API仕様確認待ち: SBI VC Trade
- API利用不可のため保留: BITPOINT（BITPOINT Japan はユーザー向けの約定履歴取得APIが確認できないため、取引所API連携の実装対象外。過去履歴はCSVインポートまたは手動登録で補完する方針）
- サービス終了のため対象外: DMM Bitcoin
- 日本向けサービス終了予定のため対象外: Bybit
- 日本居住者が利用できないため対象外: Kraken

今後追加する取引所では、最初に API 仕様、取得できる履歴の範囲、レート制限、必要権限、テスト方法を確認します。API で取得できない過去履歴や販売所・簡単売買の履歴は、手動 CRUD または CSV インポートで補完する方針です。

---

## 使用技術

- Laravel
- PostgreSQL
- Inertia.js・React
- Tailwind CSS・Vite
- 外部 API（CoinGecko 価格取得、各取引所の約定履歴同期）

---

## ER図

```mermaid
erDiagram
    users ||--o{ transactions : has
    users ||--o{ exchange_connections : has
    exchanges ||--o{ transactions : has
    exchanges ||--o{ exchange_connections : has
    portfolios ||--o{ exchange_connections : has
    assets ||--o{ transactions : has
    assets ||--o{ asset_prices : has
```
