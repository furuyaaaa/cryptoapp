import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        recovery_code: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('two-factor.challenge.store'), {
            onFinish: () => reset('code', 'recovery_code'),
        });
    };

    const toggle = (e) => {
        e.preventDefault();
        setUseRecovery((prev) => !prev);
        reset('code', 'recovery_code');
    };

    return (
        <GuestLayout>
            <Head title="Two-Factor Challenge" />

            <div className="mb-4 text-sm text-gray-600">
                {useRecovery
                    ? '認証アプリを紛失した場合、復旧コードの 1 つを入力してください。'
                    : '認証アプリに表示されている 6 桁のコードを入力してください。'}
            </div>

            <form onSubmit={submit}>
                {!useRecovery ? (
                    <div>
                        <InputLabel htmlFor="code" value="認証コード" />
                        <TextInput
                            id="code"
                            name="code"
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            value={data.code}
                            className="mt-1 block w-full tracking-widest"
                            isFocused
                            onChange={(e) => setData('code', e.target.value)}
                        />
                        <InputError message={errors.code} className="mt-2" />
                    </div>
                ) : (
                    <div>
                        <InputLabel
                            htmlFor="recovery_code"
                            value="復旧コード"
                        />
                        <TextInput
                            id="recovery_code"
                            name="recovery_code"
                            type="text"
                            autoComplete="one-time-code"
                            value={data.recovery_code}
                            className="mt-1 block w-full"
                            isFocused
                            onChange={(e) =>
                                setData('recovery_code', e.target.value)
                            }
                        />
                        <InputError
                            message={errors.recovery_code}
                            className="mt-2"
                        />
                    </div>
                )}

                <div className="mt-4 flex items-center justify-between">
                    <button
                        type="button"
                        onClick={toggle}
                        className="text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        {useRecovery
                            ? '認証コードを使う'
                            : '復旧コードを使う'}
                    </button>
                    <PrimaryButton disabled={processing}>
                        送信
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
