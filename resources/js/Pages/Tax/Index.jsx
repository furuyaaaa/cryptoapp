import AssetIcon from '@/Components/AssetIcon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const jpy = (v) =>
    new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
        maximumFractionDigits: 0,
    }).format(Math.round(v || 0));

const num = (v, digits = 8) => {
    if (v === null || v === undefined) return '-';
    const n = Number(v);
    if (!Number.isFinite(n)) return '-';
    if (Math.abs(n) >= 1) return n.toLocaleString('ja-JP', { maximumFractionDigits: Math.min(4, digits) });
    return n.toLocaleString('ja-JP', { maximumFractionDigits: digits });
};

const profitClass = (v) =>
    v > 0 ? 'text-emerald-600' : v < 0 ? 'text-rose-600' : 'text-gray-600';

const formatDate = (iso) => {
    if (!iso) return '-';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

const METHOD_DESCRIPTION = {
    moving_average:
        '購入の都度、加重平均で取得単価を更新し、売却時にその時点の単価との差分を実現損益として確定します。年度途中で継続的に計算したい場合に向いています。',
    total_average:
        '期首在庫と期中の総購入から年単位の平均取得単価を算出し、その単価で売却原価を一括計算します。計算がシンプルで確定申告に用いやすい方法です（継続適用が原則）。',
};

function SummaryCard({ label, value, tone = 'neutral', sub }) {
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
            {sub && <div className="mt-1 text-xs text-gray-500">{sub}</div>}
        </div>
    );
}

function MethodToggle({ value, options, onChange }) {
    return (
        <div className="inline-flex rounded-md border border-gray-200 bg-white p-1 shadow-sm">
            {options.map((opt) => {
                const active = value === opt.value;
                return (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => onChange(opt.value)}
                        className={`rounded px-4 py-1.5 text-sm font-medium transition ${
                            active
                                ? 'bg-indigo-600 text-white shadow'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        {opt.label}
                    </button>
                );
            })}
        </div>
    );
}

function AssetDetail({ asset }) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <tr className="hover:bg-gray-50">
                <td className="whitespace-nowrap px-4 py-3">
                    <button
                        type="button"
                        onClick={() => setOpen((o) => !o)}
                        className="group flex items-center gap-3 text-left"
                    >
                        <AssetIcon symbol={asset.symbol} iconUrl={asset.icon_url} size="md" />
                        <div>
                            <div className="text-sm font-semibold text-gray-900 group-hover:text-indigo-600">
                                {asset.symbol}
                            </div>
                            <div className="text-xs text-gray-500">{asset.name}</div>
                        </div>
                        <span className="ml-1 text-xs text-gray-400">
                            {open ? '▲' : '▼'}
                        </span>
                    </button>
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                    {num(asset.opening_amount)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                    {num(asset.buy_amount_in_year)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                    {jpy(asset.average_cost)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                    {jpy(asset.proceeds)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                    {jpy(asset.cost_of_sold)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                    {jpy(asset.sell_fees)}
                </td>
                <td
                    className={`whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-semibold ${profitClass(
                        asset.realized_gain,
                    )}`}
                >
                    {asset.realized_gain >= 0 ? '+' : ''}
                    {jpy(asset.realized_gain)}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                    {asset.sell_count}
                </td>
                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-600">
                    {num(asset.ending_amount)}
                </td>
            </tr>
            {open && (
                <tr className="bg-gray-50/60">
                    <td colSpan={10} className="px-4 py-4">
                        {asset.lots && asset.lots.length > 0 ? (
                            <div className="overflow-x-auto rounded border border-gray-200 bg-white">
                                <table className="min-w-full divide-y divide-gray-200 text-xs">
                                    <thead className="bg-gray-50 text-gray-500">
                                        <tr>
                                            <th className="px-3 py-2 text-left">売却日</th>
                                            <th className="px-3 py-2 text-right">売却数量</th>
                                            <th className="px-3 py-2 text-right">売却単価</th>
                                            <th className="px-3 py-2 text-right">譲渡収入</th>
                                            <th className="px-3 py-2 text-right">取得単価</th>
                                            <th className="px-3 py-2 text-right">譲渡原価</th>
                                            <th className="px-3 py-2 text-right">手数料</th>
                                            <th className="px-3 py-2 text-right">実現損益</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 text-gray-700">
                                        {asset.lots.map((l, i) => (
                                            <tr key={i}>
                                                <td className="px-3 py-2">{formatDate(l.executed_at)}</td>
                                                <td className="px-3 py-2 text-right font-mono">{num(l.amount)}</td>
                                                <td className="px-3 py-2 text-right font-mono">{jpy(l.price_jpy)}</td>
                                                <td className="px-3 py-2 text-right font-mono">{jpy(l.proceeds)}</td>
                                                <td className="px-3 py-2 text-right font-mono">{jpy(l.cost_basis_unit)}</td>
                                                <td className="px-3 py-2 text-right font-mono">{jpy(l.cost_basis)}</td>
                                                <td className="px-3 py-2 text-right font-mono">{jpy(l.fee_jpy)}</td>
                                                <td
                                                    className={`px-3 py-2 text-right font-mono font-semibold ${profitClass(
                                                        l.realized_gain,
                                                    )}`}
                                                >
                                                    {l.realized_gain >= 0 ? '+' : ''}
                                                    {jpy(l.realized_gain)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-xs text-gray-500">
                                この年度の売却はありません（期中の取得のみ反映）。
                            </p>
                        )}
                    </td>
                </tr>
            )}
        </>
    );
}

export default function TaxIndex({ report, filters, options }) {
    const [year, setYear] = useState(filters.year);
    const [method, setMethod] = useState(filters.method);

    const apply = (patch) => {
        const next = { year, method, ...patch };
        setYear(next.year);
        setMethod(next.method);
        router.get(route('tax.index'), next, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const exportUrl = useMemo(
        () => `${route('tax.export')}?year=${year}&method=${method}`,
        [year, method],
    );

    const totals = report.totals;
    const assets = report.assets ?? [];
    const methodLabel =
        options.methods.find((m) => m.value === report.method)?.label ?? '';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        税務計算（日本・暗号資産）
                    </h2>
                    <a
                        href={exportUrl}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        CSVエクスポート
                    </a>
                </div>
            }
        >
            <Head title="税務計算" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="rounded-lg bg-white p-6 shadow">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <label
                                    htmlFor="year"
                                    className="block text-xs font-semibold uppercase tracking-wider text-gray-500"
                                >
                                    対象年度
                                </label>
                                <select
                                    id="year"
                                    value={year}
                                    onChange={(e) => apply({ year: Number(e.target.value) })}
                                    className="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {options.years.map((y) => (
                                        <option key={y} value={y}>
                                            {y} 年
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <div className="block text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    評価方法
                                </div>
                                <div className="mt-1">
                                    <MethodToggle
                                        value={method}
                                        options={options.methods}
                                        onChange={(v) => apply({ method: v })}
                                    />
                                </div>
                            </div>
                        </div>
                        <p className="mt-4 rounded-md bg-indigo-50 px-4 py-3 text-xs text-indigo-800">
                            {METHOD_DESCRIPTION[method]}
                            <br />
                            <span className="text-indigo-600">
                                ※ 日本では暗号資産の所得計算は原則「総平均法」。届け出により「移動平均法」を選択でき、一度選んだ方法は継続適用が必要です。本ツールの結果は参考値であり、確定申告には税理士・税務署の指導に従ってください。
                            </span>
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <SummaryCard
                            label="譲渡収入 合計"
                            value={{ formatted: jpy(totals.proceeds), raw: totals.proceeds }}
                            sub={`${year}年の売却総額`}
                        />
                        <SummaryCard
                            label="譲渡原価 合計"
                            value={{
                                formatted: jpy(totals.cost_of_sold),
                                raw: totals.cost_of_sold,
                            }}
                            sub={methodLabel}
                        />
                        <SummaryCard
                            label="売却手数料 合計"
                            value={{ formatted: jpy(totals.sell_fees), raw: totals.sell_fees }}
                            sub={`売却回数: ${totals.sell_count}`}
                        />
                        <SummaryCard
                            label="実現損益 合計"
                            tone="profit"
                            value={{
                                formatted: `${totals.realized_gain >= 0 ? '+' : ''}${jpy(
                                    totals.realized_gain,
                                )}`,
                                raw: totals.realized_gain,
                            }}
                            sub="= 譲渡収入 − 譲渡原価 − 手数料"
                        />
                    </div>

                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-sm font-semibold text-gray-900">
                                銘柄別 実現損益（{year}年 / {methodLabel}）
                            </h3>
                            <p className="mt-1 text-xs text-gray-500">
                                行をクリックで売却明細を展開します。
                            </p>
                        </div>
                        {assets.length === 0 ? (
                            <div className="p-8 text-center text-sm text-gray-500">
                                {year} 年度の対象取引はありません。
                                <div className="mt-3">
                                    <Link
                                        href={route('transactions.create')}
                                        className="text-indigo-600 hover:text-indigo-800"
                                    >
                                        取引を追加する
                                    </Link>
                                </div>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                銘柄
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                期首数量
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                期中取得数量
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                平均取得単価
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                譲渡収入
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                譲渡原価
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                手数料
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                実現損益
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                売却回数
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                期末数量
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {assets.map((a) => (
                                            <AssetDetail key={a.asset_id} asset={a} />
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-gray-50 font-semibold">
                                        <tr>
                                            <td className="px-4 py-3 text-sm text-gray-700">合計</td>
                                            <td colSpan={3} />
                                            <td className="px-4 py-3 text-right font-mono text-sm text-gray-700">
                                                {jpy(totals.proceeds)}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-sm text-gray-700">
                                                {jpy(totals.cost_of_sold)}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-sm text-gray-700">
                                                {jpy(totals.sell_fees)}
                                            </td>
                                            <td
                                                className={`px-4 py-3 text-right font-mono text-sm ${profitClass(
                                                    totals.realized_gain,
                                                )}`}
                                            >
                                                {totals.realized_gain >= 0 ? '+' : ''}
                                                {jpy(totals.realized_gain)}
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-sm text-gray-700">
                                                {totals.sell_count}
                                            </td>
                                            <td />
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
