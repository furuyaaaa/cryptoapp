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

const formatDateTime = (iso) => {
    if (!iso) return '-';
    const d = new Date(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

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
                        <span
                            key={i}
                            className={className}
                            dangerouslySetInnerHTML={{ __html: label }}
                        />
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

export default function Index({ assets, filters }) {
    const flash = usePage().props.flash ?? {};
    const [q, setQ] = useState(filters.q ?? '');

    const applyFilters = (e) => {
        e?.preventDefault();
        router.get(
            route('assets.index'),
            q ? { q } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const resetFilters = () => {
        setQ('');
        router.get(route('assets.index'), {}, { preserveScroll: true, replace: true });
    };

    const handleDelete = (asset) => {
        if (asset.transactions_count > 0) {
            alert(
                `銘柄「${asset.symbol}」には ${asset.transactions_count} 件の取引履歴があるため削除できません。`,
            );
            return;
        }
        if (confirm(`銘柄「${asset.symbol} (${asset.name})」を削除します。よろしいですか？`)) {
            router.delete(route('assets.destroy', asset.id), { preserveScroll: true });
        }
    };

    const data = assets.data ?? [];
    const meta = assets.meta ?? assets;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        銘柄管理
                    </h2>
                    <Link
                        href={route('assets.create')}
                        className="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        + 銘柄を追加
                    </Link>
                </div>
            }
        >
            <Head title="銘柄管理" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}
                    {flash.error && (
                        <div className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            {flash.error}
                        </div>
                    )}

                    <form
                        onSubmit={applyFilters}
                        className="flex flex-col gap-3 rounded-lg bg-white p-4 shadow sm:flex-row sm:items-end sm:p-6"
                    >
                        <div className="flex-1">
                            <label className="mb-1 block text-xs font-medium text-gray-600">
                                検索 (シンボル・銘柄名・CoinGecko ID)
                            </label>
                            <TextInput
                                type="search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="BTC, Bitcoin, bitcoin..."
                                className="block w-full"
                            />
                        </div>
                        <div className="flex items-center justify-end gap-2">
                            {(filters.q ?? '') !== '' && (
                                <SecondaryButton type="button" onClick={resetFilters}>
                                    リセット
                                </SecondaryButton>
                            )}
                            <PrimaryButton>検索</PrimaryButton>
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
                                銘柄が登録されていません。
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                銘柄
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                CoinGecko ID
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                現在価格
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                価格更新
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                取引数
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                操作
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {data.map((a) => (
                                            <tr key={a.id} className="hover:bg-gray-50">
                                                <td className="whitespace-nowrap px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <AssetIcon
                                                            symbol={a.symbol}
                                                            iconUrl={a.icon_url}
                                                            size="md"
                                                        />
                                                        <div>
                                                            <div className="text-sm font-semibold text-gray-900">
                                                                {a.symbol}
                                                            </div>
                                                            <div className="text-xs text-gray-500">
                                                                {a.name}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-600">
                                                    {a.coingecko_id ?? (
                                                        <span className="text-gray-400">-</span>
                                                    )}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-700">
                                                    {a.latest_price_jpy > 0
                                                        ? jpy(a.latest_price_jpy)
                                                        : '-'}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-500">
                                                    {formatDateTime(a.latest_price_recorded_at)}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-700">
                                                    {a.transactions_count}
                                                </td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right text-sm">
                                                    <Link
                                                        href={route('assets.show', a.symbol)}
                                                        className="text-gray-600 hover:text-gray-900"
                                                    >
                                                        詳細
                                                    </Link>
                                                    <span className="mx-2 text-gray-300">|</span>
                                                    <Link
                                                        href={route('assets.edit', a.id)}
                                                        className="text-indigo-600 hover:text-indigo-800"
                                                    >
                                                        編集
                                                    </Link>
                                                    <span className="mx-2 text-gray-300">|</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDelete(a)}
                                                        disabled={a.transactions_count > 0}
                                                        className="text-rose-600 hover:text-rose-800 disabled:cursor-not-allowed disabled:text-gray-400"
                                                        title={
                                                            a.transactions_count > 0
                                                                ? '取引履歴があるため削除できません'
                                                                : ''
                                                        }
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
