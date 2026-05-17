import AssetIcon from '@/Components/AssetIcon';
import ChangeBadge from '@/Components/ChangeBadge';
import { Link, router } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

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

const DEFAULT_POLL_ONLY = ['portfolios', 'totals'];

/**
 * 銘柄別の評価損益テーブル。
 * pollIntervalMs を指定すると、指定間隔で Inertia の部分リロードを行う（Livewire の wire:poll に相当）。
 */
export default function ProfitLoss({
    holdings = [],
    pollOnly = DEFAULT_POLL_ONLY,
    pollIntervalMs = 30_000,
}) {
    const pollKey = JSON.stringify(pollOnly);

    useEffect(() => {
        if (!pollIntervalMs || pollIntervalMs <= 0) {
            return undefined;
        }
        const id = setInterval(() => {
            router.reload({
                only: pollOnly,
                preserveScroll: true,
                preserveState: false,
            });
        }, pollIntervalMs);

        return () => clearInterval(id);
    }, [pollIntervalMs, pollKey, pollOnly]);

    const footer = useMemo(() => {
        let profit = 0;
        let costBasis = 0;
        let valuation = 0;
        for (const h of holdings) {
            profit += Number(h.profit ?? 0);
            costBasis += Number(h.cost_basis ?? 0);
            valuation += Number(h.valuation ?? 0);
        }
        const profitRate = costBasis > 0 ? profit / costBasis : 0;

        return { profit, costBasis, valuation, profitRate };
    }, [holdings]);

    if (!holdings.length) {
        return (
            <p className="rounded-lg border border-dashed border-gray-200 bg-gray-50/80 py-10 text-center text-sm text-gray-500">
                このポートフォリオにはまだ保有資産がありません。
            </p>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm ring-1 ring-black/5">
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gradient-to-b from-gray-50 to-gray-100/80">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">
                                銘柄
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                保有数量
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                平均取得単価
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                現在価格
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                24h
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                評価額
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                取得コスト
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                損益
                            </th>
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">
                                損益率
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 bg-white">
                        {holdings.map((h) => (
                            <tr key={h.asset_id} className="transition-colors hover:bg-indigo-50/40">
                                <td className="whitespace-nowrap px-4 py-3">
                                    <Link
                                        href={route('assets.show', h.asset_id)}
                                        className="group flex items-center gap-3"
                                    >
                                        <AssetIcon symbol={h.symbol} iconUrl={h.icon_url} size="md" />
                                        <div>
                                            <div className="font-semibold text-gray-900 group-hover:text-indigo-600">
                                                {h.symbol}
                                            </div>
                                            <div className="text-xs text-gray-500">{h.name}</div>
                                        </div>
                                    </Link>
                                </td>
                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-900">
                                    {amount(h.amount)}
                                </td>
                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-600">
                                    {jpy(h.avg_buy_price)}
                                </td>
                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-600">
                                    {h.current_price_jpy > 0 ? jpy(h.current_price_jpy) : '-'}
                                </td>
                                <td className="whitespace-nowrap px-4 py-3 text-right">
                                    <ChangeBadge value={h.change_24h} />
                                </td>
                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono font-semibold text-gray-900">
                                    {jpy(h.valuation)}
                                </td>
                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-gray-600">
                                    {jpy(h.cost_basis)}
                                </td>
                                <td
                                    className={`whitespace-nowrap px-4 py-3 text-right font-mono font-semibold ${profitClass(h.profit)}`}
                                >
                                    {h.profit >= 0 ? '+' : ''}
                                    {jpy(h.profit)}
                                </td>
                                <td
                                    className={`whitespace-nowrap px-4 py-3 text-right font-mono font-semibold ${profitClass(h.profit)}`}
                                >
                                    {h.profit >= 0 ? '+' : ''}
                                    {pct(h.profit_rate)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot className="border-t-2 border-gray-200 bg-gray-50/90">
                        <tr>
                            <td
                                colSpan={5}
                                className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                合計（このポートフォリオ内）
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-bold text-gray-900">
                                {jpy(footer.valuation)}
                            </td>
                            <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-semibold text-gray-700">
                                {jpy(footer.costBasis)}
                            </td>
                            <td
                                className={`whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-bold ${profitClass(footer.profit)}`}
                            >
                                {footer.profit >= 0 ? '+' : ''}
                                {jpy(footer.profit)}
                            </td>
                            <td
                                className={`whitespace-nowrap px-4 py-3 text-right font-mono text-sm font-bold ${profitClass(footer.profit)}`}
                            >
                                {footer.profit >= 0 ? '+' : ''}
                                {pct(footer.profitRate)}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            {pollIntervalMs > 0 && (
                <div className="border-t border-gray-100 bg-gray-50/50 px-4 py-2 text-right text-xs text-gray-400">
                    約 {pollIntervalMs / 1000} 秒ごとに自動更新
                </div>
            )}
        </div>
    );
}
