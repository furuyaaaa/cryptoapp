import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import TransactionForm from './Form';

function nowForDateTimeLocal() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function Create({
    portfolios,
    assets,
    exchanges,
    types,
    defaultPortfolioId,
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        portfolio_id: defaultPortfolioId ?? '',
        asset_id: assets[0]?.id ?? '',
        exchange_id: '',
        type: 'buy',
        amount: '',
        price_jpy: '',
        fee_jpy: '',
        executed_at: nowForDateTimeLocal(),
        note: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('transactions.store'), {
            onSuccess: () => reset('amount', 'price_jpy', 'fee_jpy', 'note'),
        });
    };

    if (portfolios.length === 0) {
        return (
            <AuthenticatedLayout
                header={
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        取引を追加
                    </h2>
                }
            >
                <Head title="取引を追加" />
                <div className="py-8">
                    <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                        <div className="rounded-lg bg-white p-8 text-center shadow">
                            <p className="text-sm text-gray-500">
                                取引を登録するには先にポートフォリオを作成する必要があります。
                            </p>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    取引を追加
                </h2>
            }
        >
            <Head title="取引を追加" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <TransactionForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                        submitLabel="登録する"
                        portfolios={portfolios}
                        assets={assets}
                        exchanges={exchanges}
                        types={types}
                        cancelHref={route('portfolios.index')}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
