# Crypto Portfolio App

## 概要
複数の仮想通貨取引所に分散している資産を一元管理するアプリです。

- 通貨ごとの保有量
- 平均取得単価
- 現在価格
- 損益

を一画面で確認できます。

---

## 主な機能

- 取引履歴の登録（購入価格・数量）
- 通貨ごとの資産集計
- 現在価格の取得（API連携）
- 損益の自動計算
- 取引所別の管理

---

## 使用技術

- Laravel
- PostgreSQL
- Blade / Livewire
- 外部API（価格取得）

---

## ER図

```mermaid
erDiagram
    users ||--o{ transactions : has
    exchanges ||--o{ transactions : has
    assets ||--o{ transactions : has
    assets ||--o{ asset_prices : has
