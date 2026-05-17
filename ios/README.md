# CryptoApp（SwiftUI サンプル）

Xcode で **新規 App（SwiftUI, iOS 17+）** を作成し、以下の手順で本フォルダのソースをターゲットに追加してください。

## 手順

1. Xcode: **File → New → Project → App**（Interface: SwiftUI）
2. プロダクト名は任意（例: `CryptoApp`）。Bundle ID はご自身のチーム用に設定。
3. Finder で `ios/CryptoApp/` 以下の `.swift` ファイルを Xcode のプロジェクトナビゲータにドラッグし、**Copy items if needed** にチェック、ターゲットに追加。
4. **Signing & Capabilities** で開発チームを選択。
5. シミュレータで Laravel を `http://127.0.0.1:8000` で動かしている場合:
   - `APIConfiguration.swift` の `defaultBaseURL` をその URL に合わせる。
   - **App Transport Security**: `Info` で `NSAppTransportSecurity` → `NSExceptionDomains` → `127.0.0.1` に `NSExceptionAllowsInsecureHTTPLoads = YES`（開発専用。本番は HTTPS のみ）。

## 認証

- `POST /api/v1/auth/login`（JSON）で `token` を取得し、Keychain に保存。
- 以降 `Authorization: Bearer <token>` を付与。

2FA 有効ユーザーは、422 応答の `two_factor_required` を見て `one_time_password` を同じエンドポイントに再送してください。

## 関連ドキュメント

- API ルート: `routes/api.php`
- 振る舞いの例: `tests/Feature/Api/V1/MobileApiTest.php`
- Android 向けメモ: `../mobile/README.md`
