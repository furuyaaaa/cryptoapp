import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var session: SessionStore
    @State private var email = ""
    @State private var password = ""
    @State private var otp = ""
    @State private var needsOtp = false

    var body: some View {
        Form {
            Section("アカウント") {
                TextField("メール", text: $email)
                    .textContentType(.username)
                    .keyboardType(.emailAddress)
                    .textInputAutocapitalization(.never)
                SecureField("パスワード", text: $password)
                    .textContentType(.password)
            }
            if needsOtp {
                Section("二要素認証") {
                    TextField("6 桁コードまたは復旧コード", text: $otp)
                        .textContentType(.oneTimeCode)
                }
            }
            if let err = session.lastError {
                Section {
                    Text(err).foregroundStyle(.red)
                }
            }
            Section {
                Button(needsOtp ? "ログイン（2FA）" : "ログイン") {
                    Task {
                        await session.login(
                            email: email,
                            password: password,
                            otp: needsOtp ? (otp.isEmpty ? nil : otp) : nil
                        )
                        if session.lastError?.contains("二要素") == true {
                            needsOtp = true
                        }
                    }
                }
            }
        }
        .navigationTitle("ログイン")
    }
}
