import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import PortfolioForm from './Form';

export default function Edit({ portfolio }) {
    const { data, setData, put, processing, errors } = useForm({
        name: portfolio.name ?? '',
        description: portfolio.description ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('portfolios.update', portfolio.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    ポートフォリオを編集
                </h2>
            }
        >
            <Head title="ポートフォリオを編集" />

            <div className="py-8">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <PortfolioForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                        submitLabel="更新する"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
