import SwiftUI

struct RootView: View {
    @EnvironmentObject private var session: SessionStore

    var body: some View {
        NavigationStack {
            if session.token == nil {
                LoginView()
            } else {
                DashboardView()
            }
        }
    }
}
