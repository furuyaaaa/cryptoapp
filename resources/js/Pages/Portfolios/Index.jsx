import AssetIcon from '@/Components/AssetIcon';
import ChangeBadge from '@/Components/ChangeBadge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const jpy = (v) =>
    new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
        maximumFractionDigits: 0,
    }).format(Math.round(v || 0));

const amount = (v, digits = 8) => {
    if (v === null || v === undefined) return '-';
    const num = Number(v);
    if (Math.abs(num) >= 1) return num.toLocaleString('ja-JP', { maximumFractionDigits: 4 });
    return num.toLocaleString('ja-JP', { maximumFractionDigits: digits });
};

const pct = (v) => `${(v * 100).toFixed(2)}%`;

const profitClass = (v) =>
    v > 0 ? 'text-emerald-600' : v < 0 ? 'text-rose-600' : 'text-gray-600';

function SummaryCard({ label, value, sub, tone = 'neutral' }) {
    const toneClass =
        tone === 'profit'
            ? profitClass(Number(value?.raw ?? 0))
            : 'text-gray-900';
    return (
        <div className="rounded-lg bg-white p-6 shadow">
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className={`mt-2 text-2xl font-semibold ${toneClass}`}>
                {value?.formatted ?? value}
            </div>
            {sub && <div className={`mt-1 text-sm ${toneClass}`}>{sub}</div>}
        </div>
    );
}

function HoldingsTable({ holdings }) {
    if (!holdings.length) {
        return (
            <p className="py-8 text-center text-sm text-gray-500">
                このポートフォリオにはまだ保有資産がありません。
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">銘柄</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">保有数量</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">平均取得単価</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">現在価格</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">24h</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">評価額</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">取得コスト</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">損益</th>
                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">損益率</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 bg-white">
                    {holdings.map((h) => (
                        <tr key={h.asset_id} className="hover:bg-gray-50">
                            <td className="whitespace-nowrap px-4 py-3">
                                <Link
                                    href={route('assets.show', h.symbol)}
                                    className="group flex items-center gap-3"
                                >
                                    <AssetIcon symbol={h.symbol} iconUrl={h.icon_url} size="md" />
                                    <div>
                                        <div className="text-sm font-semibold text-gray-900 group-hover:text-indigo-600">{h.symbol}</div>
                                        <div className="text-xs text-gray-500">{h.name}</div>
                                    </div>
                                </Link>
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-900">
                                {amount(h.amount)}
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                                {jpy(h.avg_buy_price)}
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                                {h.current_price_jpy > 0 ? jpy(h.current_price_jpy) : '-'}
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                <ChangeBadge value={h.change_24h} />
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-semibold text-gray-900">
                                {jpy(h.valuation)}
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                                {jpy(h.cost_basis)}
                            </td>
                            <td className={`whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-semibold ${profitClass(h.profit)}`}>
                                {h.profit >= 0 ? '+' : ''}{jpy(h.profit)}
                            </td>
                            <td className={`whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-semibold ${profitClass(h.profit)}`}>
                                {h.profit >= 0 ? '+' : ''}{pct(h.profit_rate)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Index({ portfolios, totals }) {
    const hasData = portfolios.length > 0;
    const flash = usePage().props.flash ?? {};

    const handleDelete = (portfolio) => {
        if (
            confirm(
                `ポートフォリオ「${portfolio.name}」を削除します。\n含まれる取引もすべて削除されます。よろしいですか？`,
            )
        ) {
            router.delete(route('portfolios.destroy', portfolio.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        ポートフォリオ
                    </h2>
                    <div className="flex items-center gap-2">
                        <Link
                            href={route('portfolios.create')}
                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            + ポートフォリオ
                        </Link>
                        <Link
                            href={route('transactions.create')}
                            className="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            + 取引を追加
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="ポートフォリオ" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <SummaryCard
                            label="合計評価額"
                            value={{ formatted: jpy(totals.valuation), raw: totals.valuation }}
                        />
                        <SummaryCard
                            label="合計取得コスト"
                            value={{ formatted: jpy(totals.cost_basis), raw: totals.cost_basis }}
                        />
                        <SummaryCard
                            label="合計損益"
                            tone="profit"
                            value={{
                                formatted: `${totals.profit >= 0 ? '+' : ''}${jpy(totals.profit)}`,
                                raw: totals.profit,
                            }}
                            sub={`${totals.profit >= 0 ? '+' : ''}${pct(totals.profit_rate)}`}
                        />
                    </div>

                    {!hasData && (
                        <div className="rounded-lg bg-white p-8 text-center shadow">
                            <p className="text-sm text-gray-500">
                                ポートフォリオがまだ作成されていません。
                            </p>
                            <Link
                                href={route('portfolios.create')}
                                className="mt-4 inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                            >
                                最初のポートフォリオを作成
                            </Link>
                        </div>
                    )}

                    {portfolios.map((p) => (
                        <div key={p.id} className="overflow-hidden rounded-lg bg-white shadow">
                            <div className="flex items-start justify-between gap-4 border-b border-gray-200 p-6">
                                <div className="min-w-0 flex-1">
                                    <h3 className="text-lg font-semibold text-gray-900">{p.name}</h3>
                                    {p.description && (
                                        <p className="mt-1 whitespace-pre-wrap text-sm text-gray-500">{p.description}</p>
                                    )}
                                    <div className="mt-3 flex items-center gap-3 text-xs">
                                        <Link
                                            href={route('portfolios.edit', p.id)}
                                            className="text-indigo-600 hover:text-indigo-800"
                                        >
                                            編集
                                        </Link>
                                        <span className="text-gray-300">|</span>
                                        <button
                                            type="button"
                                            onClick={() => handleDelete(p)}
                                            className="text-rose-600 hover:text-rose-800"
                                        >
                                            削除
                                        </button>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="text-xs text-gray-500">評価額</div>
                                    <div className="text-xl font-semibold text-gray-900">{jpy(p.valuation)}</div>
                                    <div className={`mt-1 text-sm font-semibold ${profitClass(p.profit)}`}>
                                        {p.profit >= 0 ? '+' : ''}{jpy(p.profit)} ({p.profit >= 0 ? '+' : ''}{pct(p.profit_rate)})
                                    </div>
                                </div>
                            </div>
                            <HoldingsTable holdings={p.holdings} />
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
