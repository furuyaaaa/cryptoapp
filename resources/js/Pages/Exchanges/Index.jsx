import AssetIcon from '@/Components/AssetIcon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const jpy = (v) =>
    new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
        maximumFractionDigits: 0,
    }).format(Math.round(v || 0));

const amount = (v, digits = 8) => {
    if (v === null || v === undefined) return '-';
    const num = Number(v);
    if (Math.abs(num) >= 1)
        return num.toLocaleString('ja-JP', { maximumFractionDigits: 4 });
    return num.toLocaleString('ja-JP', { maximumFractionDigits: digits });
};

const pct = (v) => `${(v * 100).toFixed(2)}%`;

const profitClass = (v) =>
    v > 0 ? 'text-emerald-600' : v < 0 ? 'text-rose-600' : 'text-gray-600';

const dateLabel = (iso) => {
    if (!iso) return '-';
    const d = new Date(iso);
    return d.toLocaleString('ja-JP', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

function SummaryCard({ label, value, sub, tone = 'neutral' }) {
    const toneClass =
        tone === 'profit'
            ? profitClass(Number(value?.raw ?? 0))
            : 'text-gray-900';
    return (
        <div className="rounded-lg bg-white p-5 shadow">
            <div className="text-xs font-medium uppercase tracking-wider text-gray-500">
                {label}
            </div>
            <div className={`mt-2 text-xl font-semibold ${toneClass}`}>
                {value?.formatted ?? value}
            </div>
            {sub && <div className="mt-1 text-xs text-gray-500">{sub}</div>}
        </div>
    );
}

function ExchangeHoldingsTable({ holdings }) {
    if (!holdings.length) {
        return (
            <p className="px-6 py-6 text-center text-sm text-gray-500">
                現在この取引所で保有している資産はありません。
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            銘柄
                        </th>
                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                            保有数量
                        </th>
                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                            平均取得単価
                        </th>
                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                            現在価格
                        </th>
                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                            評価額
                        </th>
                        <th className="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                            含み損益
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 bg-white">
                    {holdings.map((h) => (
                        <tr key={h.asset_id} className="hover:bg-gray-50">
                            <td className="whitespace-nowrap px-4 py-2">
                                <Link
                                    href={route('assets.show', h.symbol)}
                                    className="group flex items-center gap-3"
                                >
                                    <AssetIcon
                                        symbol={h.symbol}
                                        iconUrl={h.icon_url}
                                        size="sm"
                                    />
                                    <div>
                                        <div className="text-sm font-semibold text-gray-900 group-hover:text-indigo-600">
                                            {h.symbol}
                                        </div>
                                        <div className="text-xs text-gray-500">
                                            {h.name}
                                        </div>
                                    </div>
                                </Link>
                            </td>
                            <td className="whitespace-nowrap px-4 py-2 text-right font-mono text-sm text-gray-900">
                                {amount(h.amount)}
                            </td>
                            <td className="whitespace-nowrap px-4 py-2 text-right font-mono text-sm text-gray-600">
                                {jpy(h.avg_buy_price)}
                            </td>
                            <td className="whitespace-nowrap px-4 py-2 text-right font-mono text-sm text-gray-600">
                                {h.current_price_jpy > 0
                                    ? jpy(h.current_price_jpy)
                                    : '-'}
                            </td>
                            <td className="whitespace-nowrap px-4 py-2 text-right font-mono text-sm font-semibold text-gray-900">
                                {jpy(h.valuation)}
                            </td>
                            <td
                                className={`whitespace-nowrap px-4 py-2 text-right font-mono text-sm font-semibold ${profitClass(h.profit)}`}
                            >
                                {h.profit >= 0 ? '+' : ''}
                                {jpy(h.profit)}
                                <span className="ml-1 text-xs font-normal">
                                    ({h.profit >= 0 ? '+' : ''}
                                    {pct(h.profit_rate)})
                                </span>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ExchangeCard({ exchange }) {
    const [open, setOpen] = useState(false);
    const hasHoldings = exchange.holdings.length > 0;

    return (
        <div className="overflow-hidden rounded-lg bg-white shadow">
            <div className="flex flex-col gap-4 border-b border-gray-200 p-6 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-600 ring-1 ring-indigo-100">
                            {exchange.name.slice(0, 2)}
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">
                                {exchange.name}
                            </h3>
                            <p className="text-xs text-gray-500">
                                最終取引: {dateLabel(exchange.last_tx_at)}
                            </p>
                        </div>
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                        <div>
                            <div className="text-xs text-gray-500">取引回数</div>
                            <div className="text-sm font-semibold text-gray-900">
                                {exchange.tx_count.toLocaleString('ja-JP')}件
                            </div>
                            <div className="mt-0.5 text-xs text-gray-500">
                                買{exchange.buy_count} / 売{exchange.sell_count}{' '}
                                / 入{exchange.transfer_in_count} / 出
                                {exchange.transfer_out_count}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-500">保有銘柄</div>
                            <div className="text-sm font-semibold text-gray-900">
                                {exchange.assets_count}銘柄
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-500">
                                取引量 (合計)
                            </div>
                            <div className="text-sm font-semibold text-gray-900">
                                {jpy(exchange.trade_volume_jpy)}
                            </div>
                            <div className="mt-0.5 text-xs text-gray-500">
                                買 {jpy(exchange.buy_volume_jpy)} / 売{' '}
                                {jpy(exchange.sell_volume_jpy)}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs text-gray-500">手数料合計</div>
                            <div className="text-sm font-semibold text-gray-900">
                                {jpy(exchange.fee_total)}
                            </div>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-3 text-right lg:min-w-[420px]">
                    <div className="rounded-md bg-gray-50 px-3 py-2">
                        <div className="text-xs text-gray-500">残高 (評価額)</div>
                        <div className="mt-1 text-base font-semibold text-gray-900">
                            {jpy(exchange.valuation)}
                        </div>
                        <div className="text-xs text-gray-500">
                            取得 {jpy(exchange.cost_basis)}
                        </div>
                    </div>
                    <div className="rounded-md bg-gray-50 px-3 py-2">
                        <div className="text-xs text-gray-500">含み損益</div>
                        <div
                            className={`mt-1 text-base font-semibold ${profitClass(exchange.profit)}`}
                        >
                            {exchange.profit >= 0 ? '+' : ''}
                            {jpy(exchange.profit)}
                        </div>
                        <div
                            className={`text-xs ${profitClass(exchange.profit)}`}
                        >
                            {exchange.profit >= 0 ? '+' : ''}
                            {pct(exchange.profit_rate)}
                        </div>
                    </div>
                    <div className="rounded-md bg-gray-50 px-3 py-2">
                        <div className="text-xs text-gray-500">実現損益</div>
                        <div
                            className={`mt-1 text-base font-semibold ${profitClass(exchange.realized_pnl)}`}
                        >
                            {exchange.realized_pnl >= 0 ? '+' : ''}
                            {jpy(exchange.realized_pnl)}
                        </div>
                        <div className="text-xs text-gray-500">手数料控除後</div>
                    </div>
                </div>
            </div>

            {hasHoldings && (
                <div className="border-t border-gray-100">
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        className="flex w-full items-center justify-between px-6 py-3 text-left text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <span>保有資産の内訳 ({exchange.assets_count}銘柄)</span>
                        <span className="text-xs text-gray-500">
                            {open ? '閉じる ▲' : '表示 ▼'}
                        </span>
                    </button>
                    {open && <ExchangeHoldingsTable holdings={exchange.holdings} />}
                </div>
            )}
        </div>
    );
}

export default function Index({ exchanges, totals }) {
    const hasData = exchanges.length > 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    取引所別集計
                </h2>
            }
        >
            <Head title="取引所別集計" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <SummaryCard
                            label="合計評価額"
                            value={{
                                formatted: jpy(totals.valuation),
                                raw: totals.valuation,
                            }}
                            sub={`取得コスト ${jpy(totals.cost_basis)}`}
                        />
                        <SummaryCard
                            label="合計含み損益"
                            tone="profit"
                            value={{
                                formatted: `${totals.profit >= 0 ? '+' : ''}${jpy(totals.profit)}`,
                                raw: totals.profit,
                            }}
                            sub={`${totals.profit >= 0 ? '+' : ''}${pct(totals.profit_rate)}`}
                        />
                        <SummaryCard
                            label="実現損益 (累計)"
                            tone="profit"
                            value={{
                                formatted: `${totals.realized_pnl >= 0 ? '+' : ''}${jpy(totals.realized_pnl)}`,
                                raw: totals.realized_pnl,
                            }}
                            sub={`手数料 ${jpy(totals.fee_total)}`}
                        />
                        <SummaryCard
                            label="取引量 (累計)"
                            value={{
                                formatted: jpy(totals.trade_volume_jpy),
                                raw: totals.trade_volume_jpy,
                            }}
                            sub={`取引 ${totals.tx_count.toLocaleString('ja-JP')}件 / ${totals.exchanges_count}取引所`}
                        />
                    </div>

                    {!hasData && (
                        <div className="rounded-lg bg-white p-8 text-center shadow">
                            <p className="text-sm text-gray-500">
                                まだ取引データがありません。
                            </p>
                            <Link
                                href={route('transactions.create')}
                                className="mt-4 inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                            >
                                最初の取引を追加
                            </Link>
                        </div>
                    )}

                    {hasData && (
                        <div className="overflow-hidden rounded-lg bg-white shadow">
                            <div className="border-b border-gray-200 px-6 py-4">
                                <h3 className="text-base font-semibold text-gray-900">
                                    取引所一覧
                                </h3>
                                <p className="mt-1 text-xs text-gray-500">
                                    評価額の降順で表示しています。
                                </p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                取引所
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                残高
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                取引量
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                手数料
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                含み損益
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                実現損益
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                取引回数
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                銘柄数
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {exchanges.map((ex) => (
                                            <tr
                                                key={ex.id}
                                                className="hover:bg-gray-50"
                                            >
                                                <td className="whitespace-nowrap px-4 py-3 text-sm font-semibold text-gray-900">
                                                    {ex.name}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-900">
                                                    {jpy(ex.valuation)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                                                    {jpy(ex.trade_volume_jpy)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                                                    {jpy(ex.fee_total)}
                                                </td>
                                                <td
                                                    className={`whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-semibold ${profitClass(ex.profit)}`}
                                                >
                                                    {ex.profit >= 0 ? '+' : ''}
                                                    {jpy(ex.profit)}
                                                </td>
                                                <td
                                                    className={`whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-semibold ${profitClass(ex.realized_pnl)}`}
                                                >
                                                    {ex.realized_pnl >= 0
                                                        ? '+'
                                                        : ''}
                                                    {jpy(ex.realized_pnl)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                                                    {ex.tx_count.toLocaleString(
                                                        'ja-JP',
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                                                    {ex.assets_count}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {exchanges.map((ex) => (
                        <ExchangeCard key={ex.id} exchange={ex} />
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
