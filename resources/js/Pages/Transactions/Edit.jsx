import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import TransactionForm from './Form';

export default function Edit({
    transaction,
    portfolios,
    assets,
    exchanges,
    types,
}) {
    const { data, setData, put, processing, errors } = useForm({
        portfolio_id: transaction.portfolio_id ?? '',
        asset_id: transaction.asset_id ?? '',
        exchange_id: transaction.exchange_id ?? '',
        type: transaction.type ?? 'buy',
        amount: transaction.amount ?? '',
        price_jpy: transaction.price_jpy ?? '',
        fee_jpy: transaction.fee_jpy ?? '',
        executed_at: transaction.executed_at ?? '',
        note: transaction.note ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('transactions.update', transaction.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    取引を編集
                </h2>
            }
        >
            <Head title="取引を編集" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <TransactionForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                        submitLabel="更新する"
                        portfolios={portfolios}
                        assets={assets}
                        exchanges={exchanges}
                        types={types}
                        cancelHref={route('transactions.index')}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
