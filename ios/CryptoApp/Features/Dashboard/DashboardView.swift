import SwiftUI

struct DashboardView: View {
    @EnvironmentObject private var session: SessionStore
    @State private var data: DashboardResponse?
    @State private var loadError: String?

    var body: some View {
        Group {
            if let data {
                List {
                    Section("評価額") {
                        Text(formatJPY(data.totals.valuation))
                    }
                    Section("取得コスト") {
                        Text(formatJPY(data.totals.costBasis))
                    }
                    Section("損益") {
                        Text(formatJPY(data.totals.profit))
                    }
                }
            } else if let loadError {
                Text(loadError).foregroundStyle(.red)
            } else {
                ProgressView("読み込み中…")
            }
        }
        .navigationTitle("ダッシュボード")
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                Button("ログアウト") {
                    Task { await session.logout() }
                }
            }
        }
        .task { await refresh() }
        .onChange(of: session.token) { _, _ in Task { await refresh() } }
    }

    private func refresh() async {
        guard session.token != nil else {
            data = nil
            return
        }
        loadError = nil
        let client = await session.apiClient()
        do {
            data = try await client.dashboard()
        } catch {
            loadError = error.localizedDescription
        }
    }

    private func formatJPY(_ v: Double) -> String {
        let f = NumberFormatter()
        f.numberStyle = .currency
        f.currencyCode = "JPY"
        f.maximumFractionDigits = 0
        return f.string(from: NSNumber(value: v)) ?? "\(Int(v))"
    }
}
