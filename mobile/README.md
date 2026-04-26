# モバイルクライアント（SwiftUI / Android）

バックエンドは **Laravel の JSON API**（`/api/v1`、Bearer Sanctum）です。Web（Inertia）とは別クライアントから同じデータを扱えます。

## SwiftUI（iOS）

実装サンプルはリポジトリ直下の **`ios/`** を参照してください。Xcode で新規 iOS App（SwiftUI）を作成し、その中に `ios/CryptoApp` のソースを取り込む運用を想定しています。

## Android（Kotlin / Jetpack Compose）

**同一の `/api/v1` エンドポイント**に対し、Retrofit / Ktor 等で `Authorization: Bearer <token>` を付与すれば接続できます。画面は Jetpack Compose で独自実装する形が一般的です（本リポジトリには Kotlin プロジェクトは含めていません）。

## API 概要

| メソッド | パス | 説明 |
|----------|------|------|
| POST | `/api/v1/auth/login` | メール・パスワード（＋必要なら `one_time_password`） |
| POST | `/api/v1/auth/logout` | 現在のトークン失効 |
| GET | `/api/v1/user` | ログイン中ユーザー |
| GET | `/api/v1/dashboard` | ダッシュボード用集計 JSON |
| GET/POST/PATCH/DELETE | `/api/v1/portfolios` … | ポートフォリオ CRUD |
| GET/POST/PATCH/DELETE | `/api/v1/transactions` … | 取引一覧・作成・更新・削除 |
| GET | `/api/v1/transactions/form` | 取引フォーム用マスタ |

詳細は `routes/api.php` と `tests/Feature/Api/V1/MobileApiTest.php` を参照してください。
