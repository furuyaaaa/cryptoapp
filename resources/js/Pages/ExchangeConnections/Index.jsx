import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';

const productOptions = {
    bitflyer: [
        { value: 'ALL_SPOT_JPY', label: 'すべてのJPY建てSpot' },
        { value: 'BTC_JPY', label: 'BTC_JPYのみ' },
    ],
    bitbank: [
        { value: 'ALL_JPY_PAIRS', label: 'すべてのJPY建て現物' },
        { value: 'btc_jpy', label: 'btc_jpyのみ' },
    ],
    coincheck: [
        { value: 'ALL_JPY_PAIRS', label: 'すべてのJPY建て取引所ペア' },
        { value: 'btc_jpy', label: 'btc_jpyのみ' },
    ],
};

const dateLabel = (iso) => {
    if (!iso) return '-';
    return new Date(iso).toLocaleString('ja-JP', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

function StatusBadge({ connection }) {
    if (connection.last_error_at) {
        return (
            <span className="inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">
                エラー
            </span>
        );
    }

    if (connection.last_synced_at) {
        return (
            <span className="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                同期済み
            </span>
        );
    }

    return (
        <span className="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
            未同期
        </span>
    );
}

function ConnectionRow({ connection }) {
    const sync = () => {
        router.post(route('exchange-connections.sync', connection.id), {}, {
            preserveScroll: true,
        });
    };

    const destroy = () => {
        if (confirm(`${connection.label} を削除します。よろしいですか？`)) {
            router.delete(route('exchange-connections.destroy', connection.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <tr className="hover:bg-gray-50">
            <td className="whitespace-nowrap px-4 py-3">
                <div className="text-sm font-semibold text-gray-900">
                    {connection.label}
                </div>
                <div className="text-xs text-gray-500">
                    {connection.exchange.name} / {connection.product_code}
                </div>
            </td>
            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                {connection.portfolio.name}
            </td>
            <td className="whitespace-nowrap px-4 py-3">
                <StatusBadge connection={connection} />
            </td>
            <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                {dateLabel(connection.last_synced_at)}
            </td>
            <td className="px-4 py-3 text-sm text-gray-600">
                {connection.last_error ? (
                    <span className="line-clamp-2 text-rose-700">
                        {connection.last_error}
                    </span>
                ) : (
                    <span className="text-gray-400">-</span>
                )}
            </td>
            <td className="whitespace-nowrap px-4 py-3 text-right">
                <div className="flex justify-end gap-2">
                    <SecondaryButton type="button" onClick={sync}>
                        同期
                    </SecondaryButton>
                    <DangerButton type="button" onClick={destroy}>
                        削除
                    </DangerButton>
                </div>
            </td>
        </tr>
    );
}

function ConnectionForm({ portfolios }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        portfolio_id: portfolios[0]?.id ?? '',
        exchange_code: 'bitflyer',
        product_code: 'ALL_SPOT_JPY',
        api_key: '',
        api_secret: '',
    });

    const setExchange = (exchangeCode) => {
        setData((values) => ({
            ...values,
            exchange_code: exchangeCode,
            product_code: productOptions[exchangeCode][0].value,
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('exchange-connections.store'), {
            preserveScroll: true,
            onSuccess: () => reset('api_key', 'api_secret'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="exchange_code" value="取引所" />
                    <select
                        id="exchange_code"
                        value={data.exchange_code}
                        onChange={(e) => setExchange(e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="bitflyer">bitFlyer</option>
                        <option value="bitbank">bitbank</option>
                        <option value="coincheck">Coincheck</option>
                    </select>
                    <InputError message={errors.exchange_code} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="portfolio_id" value="同期先ポートフォリオ" />
                    <select
                        id="portfolio_id"
                        value={data.portfolio_id}
                        onChange={(e) => setData('portfolio_id', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        {portfolios.map((portfolio) => (
                            <option key={portfolio.id} value={portfolio.id}>
                                {portfolio.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.portfolio_id} className="mt-2" />
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="product_code" value="商品コード" />
                    <select
                        id="product_code"
                        value={data.product_code}
                        onChange={(e) => setData('product_code', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        {productOptions[data.exchange_code].map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.product_code} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="api_key" value="API Key" />
                    <TextInput
                        id="api_key"
                        value={data.api_key}
                        onChange={(e) => setData('api_key', e.target.value)}
                        className="mt-1 block w-full"
                        autoComplete="off"
                    />
                    <InputError message={errors.api_key} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="api_secret" value="API Secret" />
                    <TextInput
                        id="api_secret"
                        type="password"
                        value={data.api_secret}
                        onChange={(e) => setData('api_secret', e.target.value)}
                        className="mt-1 block w-full"
                        autoComplete="off"
                    />
                    <InputError message={errors.api_secret} className="mt-2" />
                </div>
            </div>

            <div className="flex justify-end">
                <PrimaryButton disabled={processing || portfolios.length === 0}>
                    保存
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Index({ connections, portfolios }) {
    const flash = usePage().props.flash ?? {};

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    取引所連携
                </h2>
            }
        >
            <Head title="取引所連携" />

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

                    <div className="rounded-lg bg-white p-6 shadow">
                        <div className="mb-5 border-b border-gray-200 pb-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                取引所APIキー
                            </h3>
                        </div>
                        {portfolios.length > 0 ? (
                            <ConnectionForm portfolios={portfolios} />
                        ) : (
                            <p className="text-sm text-gray-500">
                                先にポートフォリオを作成してください。
                            </p>
                        )}
                    </div>

                    <div className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="border-b border-gray-200 px-6 py-4">
                            <h3 className="text-base font-semibold text-gray-900">
                                連携一覧
                            </h3>
                        </div>

                        {connections.length === 0 ? (
                            <p className="px-6 py-8 text-center text-sm text-gray-500">
                                取引所連携はまだ登録されていません。
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                連携
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                同期先
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                状態
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                最終同期
                                            </th>
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                エラー
                                            </th>
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                操作
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {connections.map((connection) => (
                                            <ConnectionRow
                                                key={connection.id}
                                                connection={connection}
                                            />
                                        ))}
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
