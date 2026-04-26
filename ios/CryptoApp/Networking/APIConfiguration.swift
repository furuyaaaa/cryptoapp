import Foundation

/// バックエンドのベース URL（末尾スラッシュなし）。開発時は Mac の Laravel に合わせて変更する。
enum APIConfiguration {
    static var defaultBaseURL: URL {
        URL(string: "http://127.0.0.1:8000")!
    }

    static var apiV1Base: URL {
        defaultBaseURL.appendingPathComponent("api/v1")
    }
}
