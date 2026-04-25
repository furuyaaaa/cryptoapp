import AssetIcon from '@/Components/AssetIcon';
import ChangeBadge from '@/Components/ChangeBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const jpy = (v) =>
    new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
        maximumFractionDigits: 0,
    }).format(Math.round(v || 0));

const jpyFine = (v) => {
    const n = Number(v || 0);
    if (Math.abs(n) >= 100) return jpy(n);
    return (
        '¥' +
        n.toLocaleString('ja-JP', {
            maximumFractionDigits: 4,
            minimumFractionDigits: 2,
        })
    );
};

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

const formatChartTick = (iso, range) => {
    if (!iso) return '';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    if (range === '24h') return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
    if (range === '7d') return `${pad(d.getMonth() + 1)}/${pad(d.getDate())} ${pad(d.getHours())}:00`;
    return `${pad(d.getMonth() + 1)}/${pad(d.getDate())}`;
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

const RANGES = [
    { key: '24h', label: '24h' },
    { key: '7d', label: '7日' },
    { key: '30d', label: '30日' },
    { key: '90d', label: '90日' },
    { key: '1y', label: '1年' },
    { key: 'all', label: '全期間' },
];

function Stat({ label, value, sub, tone = 'default' }) {
    const toneClass =
        tone === 'positive'
            ? 'text-emerald-600'
            : tone === 'negative'
              ? 'text-rose-600'
              : 'text-gray-900';
    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <div className="text-xs text-gray-500">{label}</div>
            <div className={`mt-1 text-lg font-semibold ${toneClass}`}>{value}</div>
            {sub && <div className="mt-0.5 text-xs text-gray-500">{sub}</div>}
        </div>
    );
}

function PriceTooltip({ active, payload }) {
    if (!active || !payload || !payload.length) return null;
    const p = payload[0].payload;
    return (
        <div className="rounded-md border border-gray-200 bg-white px-3 py-2 text-xs shadow">
            <div className="text-gray-500">{formatDateTime(p.recorded_at)}</div>
            <div className="font-semibold text-gray-800">{jpyFine(p.price_jpy)}</div>
            <div className="text-gray-500">
                ${p.price_usd.toLocaleString('en-US', { maximumFractionDigits: 4 })}
            </div>
        </div>
    );
}

export default function Show({ asset, prices, range, holding, transactions }) {
    const hasPrices = prices && prices.length > 0;
    const firstPrice = hasPrices ? prices[0].price_jpy : 0;
    const lastPrice = hasPrices ? prices[prices.length - 1].price_jpy : 0;
    const changeRate = hasPrices && firstPrice > 0 ? (lastPrice - firstPrice) / firstPrice : 0;
    const changeAbs = lastPrice - firstPrice;
    const changeTone = changeRate >= 0 ? 'positive' : 'negative';

    const changeRange = (next) => {
        router.get(
            route('assets.show', asset.symbol),
            { range: next },
            { preserveScroll: true, preserveState: true, replace: true }
        );
    };

    const chartData = (prices || []).map((p) => ({
        ...p,
        tick: formatChartTick(p.recorded_at, range),
    }));

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <AssetIcon symbol={asset.symbol} iconUrl={asset.icon_url} size="lg" />
                        <h2 className="truncate text-xl font-semibold leading-tight text-gray-800">
                            {asset.symbol}{' '}
                            <span className="font-normal text-gray-500">/ {asset.name}</span>
                        </h2>
                        <ChangeBadge value={asset.change_24h} className="hidden sm:inline-flex" />
                    </div>
                    <Link
                        href={route('transactions.create', { portfolio_id: '', asset_id: asset.id })}
                        className="shrink-0 inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        取引を追加
                    </Link>
                </div>
            }
        >
            <Head title={`${asset.symbol} - ${asset.name}`} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div className="rounded-lg bg-white p-4 shadow-sm">
                            <div className="text-xs text-gray-500">現在価格</div>
                            <div className="mt-1 text-lg font-semibold text-gray-900">
                                {jpyFine(asset.latest_price_jpy)}
                            </div>
                            <div className="mt-0.5 flex items-center gap-2 text-xs text-gray-500">
                                {asset.latest_price_usd ? (
                                    <span>
                                        $
                                        {asset.latest_price_usd.toLocaleString('en-US', {
                                            maximumFractionDigits: 4,
                                        })}
                                    </span>
                                ) : null}
                                <ChangeBadge value={asset.change_24h} />
                            </div>
                        </div>
                        <Stat
                            label={`変動 (${RANGES.find((r) => r.key === range)?.label ?? ''})`}
                            value={
                                hasPrices ? `${changeAbs >= 0 ? '+' : ''}${jpyFine(changeAbs)}` : '-'
                            }
                            sub={hasPrices ? `${changeRate >= 0 ? '+' : ''}${pct(changeRate)}` : null}
                            tone={hasPrices ? changeTone : 'default'}
                        />
                        <Stat
                            label="保有量"
                            value={`${num(holding.amount)} ${asset.symbol}`}
                            sub={`平均取得 ${jpyFine(holding.avg_buy_price)}`}
                        />
                        <Stat
                            label="評価損益"
                            value={`${holding.profit >= 0 ? '+' : ''}${jpy(holding.profit)}`}
                            sub={
                                holding.cost_basis > 0
                                    ? `評価額 ${jpy(holding.valuation)} / ${pct(holding.profit_rate)}`
                                    : '保有なし'
                            }
                            tone={holding.profit >= 0 ? 'positive' : 'negative'}
                        />
                    </div>

                    <div className="rounded-lg bg-white p-6 shadow-sm">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <h3 className="text-lg font-semibold text-gray-800">価格チャート</h3>
                            <div className="inline-flex rounded-md border border-gray-200 bg-gray-50 p-0.5">
                                {RANGES.map((r) => (
                                    <button
                                        key={r.key}
                                        type="button"
                                        onClick={() => changeRange(r.key)}
                                        className={`rounded px-3 py-1 text-xs font-medium transition ${
                                            range === r.key
                                                ? 'bg-white text-indigo-600 shadow-sm'
                                                : 'text-gray-600 hover:text-gray-900'
                                        }`}
                                    >
                                        {r.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {hasPrices ? (
                            <div className="h-80">
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={chartData} margin={{ top: 10, right: 20, left: 0, bottom: 0 }}>
                                        <defs>
                                            <linearGradient id="priceFill" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor="#6366f1" stopOpacity={0.35} />
                                                <stop offset="100%" stopColor="#6366f1" stopOpacity={0.02} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" vertical={false} />
                                        <XAxis
                                            dataKey="tick"
                                            tick={{ fontSize: 11, fill: '#6b7280' }}
                                            minTickGap={32}
                                        />
                                        <YAxis
                                            domain={['auto', 'auto']}
                                            tick={{ fontSize: 11, fill: '#6b7280' }}
                                            tickFormatter={(v) => {
                                                if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(1)}M`;
                                                if (v >= 1000) return `${(v / 1000).toFixed(0)}k`;
                                                return v.toFixed(0);
                                            }}
                                            width={60}
                                        />
                                        <Tooltip content={<PriceTooltip />} />
                                        <Area
                                            type="monotone"
                                            dataKey="price_jpy"
                                            stroke="#6366f1"
                                            strokeWidth={2}
                                            fill="url(#priceFill)"
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        ) : (
                            <div className="py-16 text-center text-sm text-gray-500">
                                価格履歴がまだありません。
                                <br />
                                <code className="mt-2 inline-block rounded bg-gray-100 px-2 py-1 text-xs">
                                    php artisan prices:update
                                </code>{' '}
                                を実行してください。
                            </div>
                        )}

                        {asset.latest_price_recorded_at && (
                            <div className="mt-2 text-right text-xs text-gray-400">
                                最終更新: {formatDateTime(asset.latest_price_recorded_at)}
                            </div>
                        )}
                    </div>

                    <div className="rounded-lg bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                            <h3 className="text-lg font-semibold text-gray-800">
                                取引履歴 ({transactions.length} 件)
                            </h3>
                            {holding.realized_profit !== 0 && (
                                <div className="text-sm">
                                    <span className="text-gray-500">実現損益: </span>
                                    <span
                                        className={`font-semibold ${
                                            holding.realized_profit >= 0
                                                ? 'text-emerald-600'
                                                : 'text-rose-600'
                                        }`}
                                    >
                                        {holding.realized_profit >= 0 ? '+' : ''}
                                        {jpy(holding.realized_profit)}
                                    </span>
                                </div>
                            )}
                        </div>

                        {transactions.length === 0 ? (
                            <div className="py-10 text-center text-sm text-gray-500">
                                この銘柄の取引履歴はまだありません。
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                                        <tr>
                                            <th className="px-4 py-2 text-left">日時</th>
                                            <th className="px-4 py-2 text-left">種別</th>
                                            <th className="px-4 py-2 text-right">数量</th>
                                            <th className="px-4 py-2 text-right">単価</th>
                                            <th className="px-4 py-2 text-right">手数料</th>
                                            <th className="px-4 py-2 text-right">合計</th>
                                            <th className="px-4 py-2 text-left">ポートフォリオ</th>
                                            <th className="px-4 py-2 text-left">取引所</th>
                                            <th className="px-4 py-2 text-right">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {transactions.map((tx) => {
                                            const gross = tx.amount * tx.price_jpy;
                                            const total =
                                                tx.type === 'buy' || tx.type === 'transfer_in'
                                                    ? gross + tx.fee_jpy
                                                    : gross - tx.fee_jpy;
                                            return (
                                                <tr key={tx.id} className="hover:bg-gray-50">
                                                    <td className="whitespace-nowrap px-4 py-2 text-gray-600">
                                                        {formatDateTime(tx.executed_at)}
                                                    </td>
                                                    <td className="px-4 py-2">{typeBadge(tx.type)}</td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right font-medium text-gray-800">
                                                        {num(tx.amount)}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right text-gray-700">
                                                        {jpyFine(tx.price_jpy)}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right text-gray-500">
                                                        {jpy(tx.fee_jpy)}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right font-semibold text-gray-800">
                                                        {jpy(total)}
                                                    </td>
                                                    <td className="px-4 py-2 text-gray-600">
                                                        {tx.portfolio.name}
                                                    </td>
                                                    <td className="px-4 py-2 text-gray-600">
                                                        {tx.exchange?.name ?? '-'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right">
                                                        <Link
                                                            href={route('transactions.edit', tx.id)}
                                                            className="text-indigo-600 hover:text-indigo-500"
                                                        >
                                                            編集
                                                        </Link>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
