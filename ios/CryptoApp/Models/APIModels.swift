import Foundation

struct LoginResponse: Codable {
    let token: String
    let tokenType: String
    let user: APIUser

    enum CodingKeys: String, CodingKey {
        case token
        case tokenType = "token_type"
        case user
    }
}

struct APIUser: Codable {
    let id: Int
    let name: String
    let email: String
}

struct DashboardResponse: Codable {
    let totals: DashboardTotals
    let allocation: [AllocationRow]
    let topHoldings: [TopHolding]
    let recentTransactions: [RecentTransaction]

    enum CodingKeys: String, CodingKey {
        case totals
        case allocation
        case topHoldings
        case recentTransactions
    }
}

struct DashboardTotals: Codable {
    let valuation: Double
    let costBasis: Double
    let profit: Double

    enum CodingKeys: String, CodingKey {
        case valuation
        case costBasis = "cost_basis"
        case profit
    }
}

struct AllocationRow: Codable {
    let symbol: String
    let name: String
    let valuation: Double
    let share: Double
}

struct TopHolding: Codable {
    let symbol: String
    let name: String
    let valuation: Double
}

struct RecentTransaction: Codable {
    let id: Int
    let type: String
    let amount: Double
}
