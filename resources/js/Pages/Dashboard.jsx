import AssetIcon from '@/Components/AssetIcon';
import ChangeBadge from '@/Components/ChangeBadge';
import Sparkline from '@/Components/Sparkline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Cell, Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

const jpy = (v) =>
    new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
        maximumFractionDigits: 0,
    }).format(Math.round(v || 0));

const num = (v) => {
    if (v === null || v === undefined) return '-';
    const n = Number(v);
    if (Math.abs(n) >= 1) return n.toLocaleString('ja-JP', { maximumFractionDigits: 4 });
    return n.toLocaleString('ja-JP', { maximumFractionDigits: 8 });
};

const pct = (v) =>
    `${((v || 0) * 100).toLocaleString('ja-JP', {
        maximumFractionDigits: 2,
        minimumFractionDigits: 2,
    })}%`;

const formatDateTime = (iso) => {
    if (!iso) return '-';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const typeBadge = (type) => {
    const map = {
        buy: { label: '買い', color: 'bg-emerald-100 text-emerald-700' },
        sell: { label: '売り', color: 'bg-rose-100 text-rose-700' },
        transfer_in: { label: '入庫', color: 'bg-sky-100 text-sky-700' },
        transfer_out: { label: '出庫', color: 'bg-amber-100 text-amber-700' },
    };
    const cfg = map[type] ?? { label: type, color: 'bg-gray-100 text-gray-700' };
    return (
        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${cfg.color}`}>
            {cfg.label}
        </span>
    );
};

const COLORS = [
    '#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ef4444',
    '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#84cc16',
    '#0ea5e9', '#a855f7',
];

function SummaryCard({ label, value, sub, tone = 'default' }) {
    const toneClass =
        tone === 'positive'
            ? 'text-emerald-600'
            : tone === 'negative'
              ? 'text-rose-600'
              : 'text-gray-900';

    return (
        <div className="rounded-lg bg-white p-5 shadow-sm">
            <div className="text-sm text-gray-500">{label}</div>
            <div className={`mt-2 text-2xl font-semibold ${toneClass}`}>{value}</div>
            {sub && <div className="mt-1 text-xs text-gray-500">{sub}</div>}
        </div>
    );
}

function AllocationTooltip({ active, payload }) {
    if (!active || !payload || !payload.length) return null;
    const p = payload[0];
    return (
        <div className="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs shadow">
            <div className="font-semibold text-gray-800">{p.payload.symbol}</div>
            <div className="text-gray-600">{jpy(p.payload.valuation)}</div>
            <div className="text-gray-500">{pct(p.payload.share)}</div>
        </div>
    );
}

export default function Dashboard({ totals, allocation, topHoldings, recentTransactions }) {
    const profitTone = totals.profit >= 0 ? 'positive' : 'negative';
    const hasAllocation = allocation && allocation.length > 0;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    ダッシュボード
                </h2>
            }
        >
            <Head title="ダッシュボード" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <SummaryCard label="評価額合計" value={jpy(totals.valuation)} />
                        <SummaryCard label="取得コスト合計" value={jpy(totals.cost_basis)} />
                        <SummaryCard
                            label="損益"
                            value={jpy(totals.profit)}
                            sub={pct(totals.profit_rate)}
                            tone={profitTone}
                        />
                        <SummaryCard
                            label="ポートフォリオ / 銘柄 / 取引"
                            value={`${totals.portfolios_count} / ${totals.assets_count} / ${totals.transactions_count}`}
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div className="rounded-lg bg-white p-6 shadow-sm lg:col-span-2">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-800">資産構成</h3>
                                <Link
                                    href={route('portfolios.index')}
                                    className="text-sm text-indigo-600 hover:text-indigo-500"
                                >
                                    ポートフォリオを見る →
                                </Link>
                            </div>

                            {hasAllocation ? (
                                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                                    <div className="h-72">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie
                                                    data={allocation}
                                                    dataKey="valuation"
                                                    nameKey="symbol"
                                                    innerRadius={55}
                                                    outerRadius={95}
                                                    paddingAngle={2}
                                                >
                                                    {allocation.map((_, i) => (
                                                        <Cell
                                                            key={i}
                                                            fill={COLORS[i % COLORS.length]}
                                                        />
                                                    ))}
                                                </Pie>
                                                <Tooltip content={<AllocationTooltip />} />
                                                <Legend
                                                    verticalAlign="bottom"
                                                    iconType="circle"
                                                    wrapperStyle={{ fontSize: '12px' }}
                                                />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </div>

                                    <div>
                                        <div className="mb-2 text-sm font-medium text-gray-600">
                                            Top {topHoldings.length} 銘柄
                                        </div>
                                        <ul className="divide-y divide-gray-100">
                                            {topHoldings.map((h, i) => (
                                                <li key={h.asset_id}>
                                                    <Link
                                                        href={route('assets.show', h.symbol)}
                                                        className="-mx-2 flex items-center gap-3 rounded-md px-2 py-2.5 hover:bg-gray-50"
                                                    >
                                                        <span
                                                            className="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                                                            style={{
                                                                backgroundColor:
                                                                    COLORS[i % COLORS.length],
                                                            }}
                                                        />
                                                        <AssetIcon
                                                            symbol={h.symbol}
                                                            iconUrl={h.icon_url}
                                                            size="sm"
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex items-center gap-2">
                                                                <span className="text-sm font-semibold text-gray-800">
                                                                    {h.symbol}
                                                                </span>
                                                                <ChangeBadge value={h.change_24h} />
                                                            </div>
                                                            <div className="text-xs text-gray-500">
                                                                {num(h.amount)} {h.symbol}
                                                            </div>
                                                        </div>
                                                        <Sparkline
                                                            data={h.sparkline}
                                                            width={64}
                                                            height={24}
                                                            className="hidden sm:block"
                                                        />
                                                        <div className="text-right">
                                                            <div className="text-sm font-semibold text-gray-800">
                                                                {jpy(h.valuation)}
                                                            </div>
                                                            <div
                                                                className={`text-xs ${h.profit >= 0 ? 'text-emerald-600' : 'text-rose-600'}`}
                                                            >
                                                                {h.profit >= 0 ? '+' : ''}
                                                                {pct(h.profit_rate)}
                                                            </div>
                                                        </div>
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            ) : (
                                <div className="py-12 text-center text-sm text-gray-500">
                                    まだ保有銘柄がありません。
                                    <br />
                                    <Link
                                        href={route('transactions.create')}
                                        className="mt-2 inline-block text-indigo-600 hover:text-indigo-500"
                                    >
                                        取引を追加する →
                                    </Link>
                                </div>
                            )}
                        </div>

                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <div className="mb-4 flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-800">最新の取引</h3>
                                <Link
                                    href={route('transactions.index')}
                                    className="text-sm text-indigo-600 hover:text-indigo-500"
                                >
                                    すべて見る →
                                </Link>
                            </div>

                            {recentTransactions.length === 0 ? (
                                <div className="py-8 text-center text-sm text-gray-500">
                                    取引履歴はまだありません。
                                </div>
                            ) : (
                                <ul className="divide-y divide-gray-100">
                                    {recentTransactions.map((tx) => (
                                        <li key={tx.id} className="py-3">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        {typeBadge(tx.type)}
                                                        <AssetIcon
                                                            symbol={tx.asset.symbol}
                                                            iconUrl={tx.asset.icon_url}
                                                            size="xs"
                                                        />
                                                        <Link
                                                            href={route('assets.show', tx.asset.symbol)}
                                                            className="text-sm font-semibold text-gray-800 hover:text-indigo-600"
                                                        >
                                                            {tx.asset.symbol}
                                                        </Link>
                                                    </div>
                                                    <div className="mt-1 truncate text-xs text-gray-500">
                                                        {tx.portfolio.name}
                                                        {tx.exchange && ` / ${tx.exchange.name}`}
                                                    </div>
                                                    <div className="mt-0.5 text-xs text-gray-400">
                                                        {formatDateTime(tx.executed_at)}
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="text-sm font-medium text-gray-800">
                                                        {num(tx.amount)}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        @ {jpy(tx.price_jpy)}
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
