import AssetIcon from '@/Components/AssetIcon';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

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

function SelectInput({ id, value, onChange, children, className = '' }) {
    return (
        <select
            id={id}
            name={id}
            value={value ?? ''}
            onChange={onChange}
            className={
                'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 ' +
                className
            }
        >
            {children}
        </select>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) return null;

    return (
        <nav className="flex flex-wrap items-center justify-center gap-1">
            {links.map((link, i) => {
                const label = link.label
                    .replace('&laquo; Previous', '‹ 前へ')
                    .replace('Next &raquo;', '次へ ›');
                const className = `inline-flex items-center rounded-md border px-3 py-1.5 text-sm ${
                    link.active
                        ? 'border-indigo-500 bg-indigo-500 text-white'
                        : link.url
                          ? 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                          : 'cursor-not-allowed border-gray-200 bg-gray-50 text-gray-400'
                }`;

                if (!link.url) {
                    return (
                        <span key={i} className={className} dangerouslySetInnerHTML={{ __html: label }} />
                    );
                }
                return (
                    <Link
                        key={i}
                        href={link.url}
                        preserveScroll
                        preserveState
                        className={className}
                        dangerouslySetInnerHTML={{ __html: label }}
                    />
                );
            })}
        </nav>
    );
}

export default function Index({ transactions, filters, filterOptions }) {
    const flash = usePage().props.flash ?? {};
    const [local, setLocal] = useState({
        portfolio_id: filters.portfolio_id ?? '',
        asset_id: filters.asset_id ?? '',
        type: filters.type ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
        q: filters.q ?? '',
    });

    const applyFilters = (e) => {
        e?.preventDefault();
        const params = Object.fromEntries(
            Object.entries(local).filter(([, v]) => v !== '' && v !== null && v !== undefined),
        );
        router.get(route('transactions.index'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setLocal({ portfolio_id: '', asset_id: '', type: '', from: '', to: '', q: '' });
        router.get(route('transactions.index'), {}, { preserveScroll: true, replace: true });
    };

    const handleDelete = (tx) => {
        if (confirm(`${formatDateTime(tx.executed_at)} の ${tx.asset?.symbol} ${tx.type} 取引を削除します。よろしいですか？`)) {
            router.delete(route('transactions.destroy', tx.id), { preserveScroll: true });
        }
    };

    const data = transactions.data ?? [];
    const meta = transactions.meta ?? transactions;
    const hasFilters = Object.values(local).some((v) => v !== '' && v !== null && v !== undefined);

    const exportUrl = (() => {
        const params = Object.fromEntries(
            Object.entries(filters ?? {}).filter(([, v]) => v !== '' && v !== null && v !== undefined),
        );
        return route('transactions.export', params);
    })();

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        取引履歴
                    </h2>
                    <div className="flex items-center gap-2">
                        <a
                            href={exportUrl}
                            className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            title="現在の絞り込み条件でCSVをダウンロードします"
                        >
                            <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fillRule="evenodd" d="M10 3a1 1 0 011 1v8.586l2.293-2.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V4a1 1 0 011-1zm-7 13a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clipRule="evenodd" />
                            </svg>
                            CSV
                        </a>
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
            <Head title="取引履歴" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form
                        onSubmit={applyFilters}
                        className="rounded-lg bg-white p-4 shadow sm:p-6"
                    >
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                            <div className="lg:col-span-2">
                                <label className="mb-1 block text-xs font-medium text-gray-600">キーワード（銘柄・メモ）</label>
                                <TextInput
                                    type="search"
                                    value={local.q}
                                    onChange={(e) => setLocal({ ...local, q: e.target.value })}
                                    placeholder="BTC, Bitcoin, DCA..."
                                    className="block w-full"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">ポートフォリオ</label>
                                <SelectInput
                                    id="portfolio_id"
                                    value={local.portfolio_id}
                                    onChange={(e) => setLocal({ ...local, portfolio_id: e.target.value })}
                                >
                                    <option value="">- すべて -</option>
                                    {filterOptions.portfolios.map((p) => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </SelectInput>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">銘柄</label>
                                <SelectInput
                                    id="asset_id"
                                    value={local.asset_id}
                                    onChange={(e) => setLocal({ ...local, asset_id: e.target.value })}
                                >
                                    <option value="">- すべて -</option>
                                    {filterOptions.assets.map((a) => (
                                        <option key={a.id} value={a.id}>{a.symbol}</option>
                                    ))}
                                </SelectInput>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">種別</label>
                                <SelectInput
                                    id="type"
                                    value={local.type}
                                    onChange={(e) => setLocal({ ...local, type: e.target.value })}
                                >
                                    <option value="">- すべて -</option>
                                    {filterOptions.types.map((t) => (
                                        <option key={t.value} value={t.value}>{t.label}</option>
                                    ))}
                                </SelectInput>
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">期間 (開始)</label>
                                <TextInput
                                    type="date"
                                    value={local.from}
                                    onChange={(e) => setLocal({ ...local, from: e.target.value })}
                                    className="block w-full"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium text-gray-600">期間 (終了)</label>
                                <TextInput
                                    type="date"
                                    value={local.to}
                                    onChange={(e) => setLocal({ ...local, to: e.target.value })}
                                    className="block w-full"
                                />
                            </div>
                        </div>

                        <div className="mt-4 flex items-center justify-end gap-2">
                            {hasFilters && (
                                <SecondaryButton type="button" onClick={resetFilters}>
                                    リセット
                                </SecondaryButton>
                            )}
                            <PrimaryButton>適用</PrimaryButton>
                        </div>
                    </form>

                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-4 py-3 text-sm text-gray-600">
                            {meta.total ?? data.length} 件
                            {meta.from && meta.to && (
                                <span className="ml-2 text-gray-400">
                                    ({meta.from} - {meta.to} 件目)
                                </span>
                            )}
                        </div>
                        {data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-gray-500">
                                該当する取引がありません。
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">日時</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">種別</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">銘柄</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">数量</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">単価</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">手数料</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">ポートフォリオ</th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">取引所</th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {data.map((tx) => (
                                            <tr key={tx.id} className="hover:bg-gray-50">
                                                <td className="whitespace-nowrap px-4 py-3 font-mono text-sm text-gray-700">
                                                    {formatDateTime(tx.executed_at)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3">{typeBadge(tx.type)}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm">
                                                    {tx.asset?.symbol ? (
                                                        <Link
                                                            href={route('assets.show', tx.asset.symbol)}
                                                            className="group flex items-center gap-2"
                                                        >
                                                            <AssetIcon
                                                                symbol={tx.asset.symbol}
                                                                iconUrl={tx.asset.icon_url}
                                                                size="sm"
                                                            />
                                                            <div>
                                                                <div className="font-semibold text-gray-900 group-hover:text-indigo-600">{tx.asset.symbol}</div>
                                                                <div className="text-xs text-gray-500">{tx.asset?.name}</div>
                                                            </div>
                                                        </Link>
                                                    ) : (
                                                        <span className="text-gray-500">-</span>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-900">
                                                    {num(tx.amount)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-700">
                                                    {jpy(tx.price_jpy)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-500">
                                                    {Number(tx.fee_jpy) > 0 ? jpy(tx.fee_jpy) : '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                                    {tx.portfolio?.name}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-500">
                                                    {tx.exchange?.name ?? '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right text-sm">
                                                    <Link
                                                        href={route('transactions.edit', tx.id)}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        編集
                                                    </Link>
                                                    <span className="mx-2 text-gray-300">|</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(tx)}
                                                        className="text-rose-600 hover:text-rose-800"
                                                    >
                                                        削除
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {meta.links && <Pagination links={meta.links} />}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
