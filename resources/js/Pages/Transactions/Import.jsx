import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Import({ portfolios }) {
    const flash = usePage().props.flash ?? {};
    const importErrors = flash.import_errors ?? [];
    const preview = flash.import_preview ?? null;

    const { data, setData, post, processing, errors } = useForm({
        action: 'preview',
        portfolio_id: portfolios[0]?.id ?? '',
        csv_file: null,
        import_token: '',
    });

    const previewCsv = (e) => {
        e.preventDefault();
        post(route('transactions.import.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const importCsv = (e) => {
        e.preventDefault();
        router.post(
            route('transactions.import.store'),
            {
                action: 'import',
                import_token: preview.token,
                portfolio_id: preview.portfolio_id ?? data.portfolio_id,
            },
            { preserveScroll: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        CSVインポート
                    </h2>
                    <Link href={route('transactions.index')}>
                        <SecondaryButton type="button">取引履歴へ戻る</SecondaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="CSVインポート" />

            <div className="py-8">
                <div className="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    {importErrors.length > 0 && (
                        <div className="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <div className="font-semibold">CSVを取り込めませんでした。</div>
                            <ul className="mt-2 list-disc space-y-1 ps-5">
                                {importErrors.map((message, index) => (
                                    <li key={index}>{message}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {preview && (
                        <div className="space-y-4 rounded-lg bg-white p-6 shadow">
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <div className="rounded-md border border-gray-200 p-3">
                                    <div className="text-xs text-gray-500">総行数</div>
                                    <div className="mt-1 text-2xl font-semibold text-gray-900">{preview.total}</div>
                                </div>
                                <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3">
                                    <div className="text-xs text-emerald-700">登録予定</div>
                                    <div className="mt-1 text-2xl font-semibold text-emerald-800">{preview.importable}</div>
                                </div>
                                <div className="rounded-md border border-amber-200 bg-amber-50 p-3">
                                    <div className="text-xs text-amber-700">重複スキップ</div>
                                    <div className="mt-1 text-2xl font-semibold text-amber-800">{preview.skipped}</div>
                                </div>
                                <div className="rounded-md border border-sky-200 bg-sky-50 p-3">
                                    <div className="text-xs text-sky-700">新規銘柄</div>
                                    <div className="mt-1 text-2xl font-semibold text-sky-800">{preview.create_assets}</div>
                                </div>
                            </div>

                            <div className="overflow-x-auto rounded-md border border-gray-200">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-600">行</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-600">状態</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-600">日時</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-600">種別</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-600">銘柄</th>
                                            <th className="px-3 py-2 text-right font-semibold text-gray-600">数量</th>
                                            <th className="px-3 py-2 text-right font-semibold text-gray-600">単価</th>
                                            <th className="px-3 py-2 text-left font-semibold text-gray-600">ポートフォリオ</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {preview.rows.map((row) => (
                                            <tr key={row.line}>
                                                <td className="px-3 py-2 text-gray-500">{row.line}</td>
                                                <td className="px-3 py-2">
                                                    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${row.status === 'skip' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                                        {row.status === 'skip' ? '重複' : '登録'}
                                                    </span>
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-2 font-mono text-gray-700">{row.executed_at}</td>
                                                <td className="px-3 py-2 text-gray-700">{row.type}</td>
                                                <td className="px-3 py-2 font-semibold text-gray-900">
                                                    {row.symbol}
                                                    {row.will_create_asset && (
                                                        <span className="ms-2 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700">
                                                            新規
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right font-mono text-gray-700">{row.amount}</td>
                                                <td className="px-3 py-2 text-right font-mono text-gray-700">{Number(row.price_jpy).toLocaleString('ja-JP')}</td>
                                                <td className="px-3 py-2 text-gray-700">{row.portfolio}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <div className="flex justify-end">
                                <PrimaryButton onClick={importCsv} disabled={processing || preview.importable === 0}>
                                    この内容で取り込む
                                </PrimaryButton>
                            </div>
                        </div>
                    )}

                    <form onSubmit={previewCsv} className="space-y-6 rounded-lg bg-white p-6 shadow">
                        <div>
                            <InputLabel htmlFor="portfolio_id" value="既定のポートフォリオ" />
                            <select
                                id="portfolio_id"
                                value={data.portfolio_id}
                                onChange={(e) => setData('portfolio_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">CSVのポートフォリオ列を使う</option>
                                {portfolios.map((portfolio) => (
                                    <option key={portfolio.id} value={portfolio.id}>
                                        {portfolio.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.portfolio_id} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="csv_file" value="CSVファイル" />
                            <input
                                id="csv_file"
                                type="file"
                                accept=".csv,text/csv,text/plain"
                                onChange={(e) => setData('csv_file', e.target.files?.[0] ?? null)}
                                className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 shadow-sm file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100 focus:border-indigo-500 focus:outline-none focus:ring-indigo-500"
                            />
                            <InputError message={errors.csv_file} className="mt-2" />
                        </div>

                        <div className="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            対応列: <code>executed_at</code>, <code>type</code>, <code>symbol</code>, <code>amount</code>, <code>price_jpy</code>, <code>fee_jpy</code>, <code>exchange</code>, <code>portfolio</code>, <code>note</code>, <code>external_id</code>
                        </div>

                        <div className="flex justify-end">
                            <PrimaryButton disabled={processing || !data.csv_file}>
                                プレビュー
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
