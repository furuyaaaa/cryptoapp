import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import AssetForm from './Form';

export default function Edit({ asset }) {
    const { data, setData, put, processing, errors } = useForm({
        symbol: asset.symbol ?? '',
        name: asset.name ?? '',
        coingecko_id: asset.coingecko_id ?? '',
        icon_url: asset.icon_url ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('assets.update', asset.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    銘柄を編集: {asset.symbol}
                </h2>
            }
        >
            <Head title={`銘柄を編集 - ${asset.symbol}`} />

            <div className="py-8">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <AssetForm
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
