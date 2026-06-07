import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Import({ portfolios }) {
    const flash = usePage().props.flash ?? {};
    const importErrors = flash.import_errors ?? [];

    const { data, setData, post, processing, errors } = useForm({
        portfolio_id: portfolios[0]?.id ?? '',
        csv_file: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('transactions.import.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
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

                    <form onSubmit={submit} className="space-y-6 rounded-lg bg-white p-6 shadow">
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
                                取り込む
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
