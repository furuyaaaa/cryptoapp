import Combine
import Foundation

@MainActor
final class SessionStore: ObservableObject {
    @Published private(set) var token: String?
    @Published private(set) var userEmail: String?
    @Published var lastError: String?

    init() {
        token = KeychainTokenStore.read()
    }

    private func client() async -> APIClient {
        await APIClient(token: token)
    }

    func login(email: String, password: String, otp: String? = nil) async {
        lastError = nil
        do {
            let c = await client()
            let res = try await c.login(email: email, password: password, oneTimePassword: otp)
            token = res.token
            userEmail = res.user.email
        } catch {
            lastError = error.localizedDescription
        }
    }

    func logout() async {
        lastError = nil
        do {
            let c = await client()
            try await c.logout()
            token = nil
            userEmail = nil
        } catch {
            lastError = error.localizedDescription
        }
    }

    func apiClient() async -> APIClient {
        await APIClient(token: token)
    }
}
