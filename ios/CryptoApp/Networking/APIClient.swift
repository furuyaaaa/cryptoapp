import Foundation

enum APIError: LocalizedError {
    case invalidURL
    case httpStatus(Int, String?)
    case decoding(Error)

    var errorDescription: String? {
        switch self {
        case .invalidURL:
            return "無効な URL です。"
        case let .httpStatus(code, body):
            return "HTTP \(code): \(body ?? "")"
        case let .decoding(err):
            return "JSON の解釈に失敗しました: \(err.localizedDescription)"
        }
    }
}

/// `/api/v1` 向けの薄い URLSession ラッパ。
actor APIClient {
    private let session: URLSession
    private var token: String?

    init(session: URLSession = .shared, token: String? = KeychainTokenStore.read()) {
        self.session = session
        self.token = token
    }

    func setToken(_ token: String?) {
        self.token = token
        if let token {
            KeychainTokenStore.save(token)
        } else {
            KeychainTokenStore.delete()
        }
    }

    private func makeRequest(path: String, method: String, jsonBody: [String: Any]? = nil) throws -> URLRequest {
        let url = APIConfiguration.apiV1Base.appendingPathComponent(path)
        var req = URLRequest(url: url)
        req.httpMethod = method
        req.setValue("application/json", forHTTPHeaderField: "Accept")
        if let token {
            req.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }
        if let jsonBody {
            req.setValue("application/json", forHTTPHeaderField: "Content-Type")
            req.httpBody = try JSONSerialization.data(withJSONObject: jsonBody)
        }
        return req
    }

    func login(email: String, password: String, oneTimePassword: String? = nil) async throws -> LoginResponse {
        var body: [String: Any] = [
            "email": email,
            "password": password,
            "device_name": "swiftui-ios",
        ]
        if let oneTimePassword {
            body["one_time_password"] = oneTimePassword
        }
        let req = try makeRequest(path: "auth/login", method: "POST", jsonBody: body)
        let (data, resp) = try await session.data(for: req)
        guard let http = resp as? HTTPURLResponse else { throw APIError.invalidURL }
        if http.statusCode == 422 {
            if let obj = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
               (obj["two_factor_required"] as? Bool) == true {
                throw APIError.httpStatus(422, "二要素認証コードが必要です。")
            }
        }
        guard (200 ... 299).contains(http.statusCode) else {
            let text = String(data: data, encoding: .utf8)
            throw APIError.httpStatus(http.statusCode, text)
        }
        do {
            let decoded = try JSONDecoder().decode(LoginResponse.self, from: data)
            await setToken(decoded.token)
            return decoded
        } catch {
            throw APIError.decoding(error)
        }
    }

    func logout() async throws {
        let req = try makeRequest(path: "auth/logout", method: "POST")
        let (_, resp) = try await session.data(for: req)
        guard let http = resp as? HTTPURLResponse else { throw APIError.invalidURL }
        guard (200 ... 299).contains(http.statusCode) else {
            throw APIError.httpStatus(http.statusCode, nil)
        }
        await setToken(nil)
    }

    func dashboard() async throws -> DashboardResponse {
        let req = try makeRequest(path: "dashboard", method: "GET")
        let (data, resp) = try await session.data(for: req)
        guard let http = resp as? HTTPURLResponse else { throw APIError.invalidURL }
        guard (200 ... 299).contains(http.statusCode) else {
            throw APIError.httpStatus(http.statusCode, String(data: data, encoding: .utf8))
        }
        do {
            return try JSONDecoder().decode(DashboardResponse.self, from: data)
        } catch {
            throw APIError.decoding(error)
        }
    }
}
