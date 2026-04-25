import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import AssetForm from './Form';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        symbol: '',
        name: '',
        coingecko_id: '',
        icon_url: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('assets.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    銘柄を追加
                </h2>
            }
        >
            <Head title="銘柄を追加" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <AssetForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                        submitLabel="登録する"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
