# データベース設計書（cryptoapp）

本書はアプリケーション用スキーマの概要です。**正本は `database/migrations/` 内のマイグレーション**です。マイグレーションと矛盾する場合はマイグレーションを優先してください。

## 1. 前提

| 項目 | 内容 |
|------|------|
| RDBMS | PostgreSQL（接続ドライバ `pgsql`） |
| ORM | Laravel Eloquent |
| マイグレーション実行 | `php artisan migrate` |

## 2. ER 図（論理）

```mermaid
erDiagram
    users ||--o{ portfolios : owns
    users ||--o{ sessions : has
    portfolios ||--o{ transactions : contains
    assets ||--o{ transactions : ""
    exchanges ||--o{ transactions : optional
    assets ||--o{ asset_prices : priced

    users {
        bigint id PK
        string name
        string email UK
        timestamp email_verified_at
        string password
        boolean is_admin
        text two_factor_secret
        text two_factor_recovery_codes
        timestamp two_factor_confirmed_at
        string remember_token
        timestamps
    }

    portfolios {
        bigint id PK
        bigint user_id FK
        string name
        text description
        timestamps
    }

    assets {
        bigint id PK
        string symbol UK
        string name
        string coingecko_id
        string icon_url
        timestamps
    }

    exchanges {
        bigint id PK
        string name
        string code UK
        string country
        timestamps
    }

    transactions {
        bigint id PK
        bigint portfolio_id FK
        bigint asset_id FK
        bigint exchange_id FK_null
        enum type
        decimal amount
        decimal price_jpy
        decimal fee_jpy
        timestamp executed_at
        text note
        timestamps
    }

    asset_prices {
        bigint id PK
        bigint asset_id FK
        decimal price_jpy
        decimal price_usd
        timestamp recorded_at
        timestamps
        UK asset_recorded
    }
```

## 3. ドメインテーブル

### 3.1 `users`

| カラム | 型（論理） | NULL | 制約・備考 |
|--------|------------|------|------------|
| `id` | bigint | NO | PK |
| `name` | string | NO | |
| `email` | string | NO | UNIQUE |
| `email_verified_at` | timestamp | YES | |
| `password` | string | NO | ハッシュ保存想定 |
| `is_admin` | boolean | NO | 既定 `false` |
| `two_factor_secret` | text | YES | アプリ側で暗号化して保存 |
| `two_factor_recovery_codes` | text | YES | JSON 配列を暗号化して保存 |
| `two_factor_confirmed_at` | timestamp | YES | 2FA 有効化確認済み時刻 |
| `remember_token` | string | YES | |
| `created_at`, `updated_at` | timestamp | YES | |

### 3.2 `portfolios`

| カラム | 型（論理） | NULL | 制約・備考 |
|--------|------------|------|------------|
| `id` | bigint | NO | PK |
| `user_id` | bigint | NO | FK → `users.id`、**ON DELETE CASCADE** |
| `name` | string | NO | |
| `description` | text | YES | |
| `created_at`, `updated_at` | timestamp | YES | |

### 3.3 `assets`（銘柄マスタ）

| カラム | 型（論理） | NULL | 制約・備考 |
|--------|------------|------|------------|
| `id` | bigint | NO | PK |
| `symbol` | string | NO | **UNIQUE** |
| `name` | string | NO | |
| `coingecko_id` | string | YES | INDEX |
| `icon_url` | string | YES | |
| `created_at`, `updated_at` | timestamp | YES | |

### 3.4 `exchanges`（取引所マスタ）

| カラム | 型（論理） | NULL | 制約・備考 |
|--------|------------|------|------------|
| `id` | bigint | NO | PK |
| `name` | string | NO | |
| `code` | string | NO | **UNIQUE** |
| `country` | string | YES | |
| `created_at`, `updated_at` | timestamp | YES | |

### 3.5 `transactions`

| カラム | 型（論理） | NULL | 制約・備考 |
|--------|------------|------|------------|
| `id` | bigint | NO | PK |
| `portfolio_id` | bigint | NO | FK → `portfolios.id`、**ON DELETE CASCADE** |
| `asset_id` | bigint | NO | FK → `assets.id`、**ON DELETE RESTRICT** |
| `exchange_id` | bigint | YES | FK → `exchanges.id`、**ON DELETE SET NULL** |
| `type` | enum | NO | 許容値: `buy`, `sell`, `transfer_in`, `transfer_out` |
| `amount` | decimal(20,8) | NO | 数量 |
| `price_jpy` | decimal(20,8) | NO | 実行時単価（円） |
| `fee_jpy` | decimal(20,8) | NO | 既定 `0` |
| `executed_at` | timestamp | NO | INDEX |
| `note` | text | YES | |
| `created_at`, `updated_at` | timestamp | YES | |

### 3.6 `asset_prices`（価格履歴）

| カラム | 型（論理） | NULL | 制約・備考 |
|--------|------------|------|------------|
| `id` | bigint | NO | PK |
| `asset_id` | bigint | NO | FK → `assets.id`、**ON DELETE CASCADE** |
| `price_jpy` | decimal(20,8) | NO | |
| `price_usd` | decimal(20,8) | NO | |
| `recorded_at` | timestamp | NO | INDEX |
| `created_at`, `updated_at` | timestamp | YES | |
| （複合） | | | **UNIQUE(`asset_id`, `recorded_at`)** — 同一資産・同一時刻の重複防止 |

## 4. Laravel フレームワーク用テーブル

### 4.1 認証・セッション

| テーブル | 用途 |
|----------|------|
| `password_reset_tokens` | パスワードリセット（`email` PK） |
| `sessions` | DB ドライバセッション（`user_id` は nullable、INDEX） |

### 4.2 キャッシュ

| テーブル | 用途 |
|----------|------|
| `cache` | DB キャッシュストア |
| `cache_locks` | キャッシュロック |

### 4.3 キュー

| テーブル | 用途 |
|----------|------|
| `jobs` | キュー投入ジョブ |
| `job_batches` | バッチジョブ |
| `failed_jobs` | 失敗ジョブ（`uuid` UNIQUE） |

## 5. 外部キー削除時の挙動（まとめ）

| 子 | 親 | ON DELETE |
|----|-----|-----------|
| `portfolios` | `users` | CASCADE |
| `transactions` | `portfolios` | CASCADE |
| `transactions` | `assets` | RESTRICT |
| `transactions` | `exchanges` | SET NULL（nullable FK） |
| `asset_prices` | `assets` | CASCADE |

## 6. 対応モデル（参考）

| テーブル | Eloquent モデル |
|----------|-----------------|
| `users` | `App\Models\User` |
| `portfolios` | `App\Models\Portfolio` |
| `assets` | `App\Models\Asset` |
| `exchanges` | `App\Models\Exchange` |
| `transactions` | `App\Models\Transaction` |
| `asset_prices` | `App\Models\AssetPrice` |

## 7. マイグレーション一覧（対応表）

| ファイル | 内容 |
|----------|------|
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` |
| `2026_03_14_032111_create_portfolios_table.php` | `portfolios` |
| `2026_04_21_114723_create_exchanges_table.php` | `exchanges` |
| `2026_04_21_205551_create_assets_table.php` | `assets` |
| `2026_04_21_205554_create_transactions_table.php` | `transactions` |
| `2026_04_21_205556_create_asset_prices_table.php` | `asset_prices` |
| `2026_04_22_000001_add_is_admin_to_users_table.php` | `users.is_admin` |
| `2026_04_24_111830_add_two_factor_columns_to_users_table.php` | `users` 2FA カラム |
