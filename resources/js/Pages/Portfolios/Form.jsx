import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';

export default function PortfolioForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
}) {
    return (
        <form
            onSubmit={onSubmit}
            className="space-y-6 rounded-lg bg-white p-6 shadow sm:p-8"
        >
            <div>
                <InputLabel htmlFor="name" value="ポートフォリオ名" />
                <TextInput
                    id="name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="mt-1 block w-full"
                    placeholder="例: メインポートフォリオ / 長期保有用"
                    maxLength={100}
                    required
                    autoFocus
                />
                <InputError className="mt-2" message={errors.name} />
            </div>

            <div>
                <InputLabel htmlFor="description" value="説明 (任意)" />
                <textarea
                    id="description"
                    value={data.description ?? ''}
                    onChange={(e) => setData('description', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={3}
                    maxLength={1000}
                    placeholder="このポートフォリオの目的や方針など"
                />
                <InputError className="mt-2" message={errors.description} />
            </div>

            <div className="flex items-center justify-end gap-3">
                <Link href={route('portfolios.index')}>
                    <SecondaryButton type="button">キャンセル</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
