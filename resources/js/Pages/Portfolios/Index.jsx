import ProfitLoss from '@/Components/ProfitLoss';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

/** Inertia 部分リロード用（参照を固定し ProfitLoss のポーリング effect が無駄に再実行されないようにする） */
const PORTFOLIOS_PAGE_POLL_ONLY = ['portfolios', 'totals'];

const jpy = (v) =>
    new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
        maximumFractionDigits: 0,
    }).format(Math.round(v || 0));

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

                    {portfolios.map((p, i) => (
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
                            <ProfitLoss
                                holdings={p.holdings}
                                pollIntervalMs={i === 0 ? 30_000 : 0}
                                pollOnly={PORTFOLIOS_PAGE_POLL_ONLY}
                            />
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
