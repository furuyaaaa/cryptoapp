import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm, usePage } from '@inertiajs/react';

export default function TwoFactorAuthenticationForm({
    className = '',
    twoFactorSetup,
}) {
    const { auth, flash } = usePage().props;
    const enabled = !!auth?.two_factor_enabled;
    const pending = !!auth?.two_factor_pending;
    const recoveryCodes = flash?.recoveryCodes || null;

    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({});
    const regenerateForm = useForm({});

    const enable = (e) => {
        e.preventDefault();
        enableForm.post(route('two-factor.store'), { preserveScroll: true });
    };

    const confirm = (e) => {
        e.preventDefault();
        confirmForm.post(route('two-factor.confirm'), {
            preserveScroll: true,
            onSuccess: () => confirmForm.reset('code'),
        });
    };

    const disable = (e) => {
        e.preventDefault();
        disableForm.delete(route('two-factor.destroy'), {
            preserveScroll: true,
        });
    };

    const regenerate = (e) => {
        e.preventDefault();
        regenerateForm.post(route('two-factor.recovery-codes'), {
            preserveScroll: true,
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Two-Factor Authentication
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    ログイン時に 6 桁のワンタイムコードを要求し、パスワード漏洩時のアカウント乗っ取りを防ぎます。
                </p>
            </header>

            <div className="mt-6 space-y-4">
                {!enabled && !pending && (
                    <div>
                        <p className="text-sm text-gray-700">
                            2FA は現在 <span className="font-semibold text-red-600">無効</span> です。
                        </p>
                        <form onSubmit={enable} className="mt-4">
                            <PrimaryButton disabled={enableForm.processing}>
                                2FA を有効化する
                            </PrimaryButton>
                        </form>
                    </div>
                )}

                {pending && twoFactorSetup && (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-700">
                            認証アプリ（Google Authenticator, 1Password など）で下記 QR をスキャンし、表示されたコードを入力してください。
                        </p>
                        <div
                            className="inline-block rounded bg-white p-2 ring-1 ring-gray-200"
                            dangerouslySetInnerHTML={{ __html: twoFactorSetup.qr }}
                        />
                        <p className="break-all text-xs text-gray-500">
                            手動で登録する場合のシークレット:{' '}
                            <span className="font-mono">
                                {twoFactorSetup.secret}
                            </span>
                        </p>

                        <form onSubmit={confirm} className="space-y-3">
                            <div>
                                <InputLabel
                                    htmlFor="two-factor-code"
                                    value="6桁コード"
                                />
                                <TextInput
                                    id="two-factor-code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    className="mt-1 block w-40 tracking-widest"
                                    value={confirmForm.data.code}
                                    onChange={(e) =>
                                        confirmForm.setData(
                                            'code',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    className="mt-2"
                                    message={confirmForm.errors.code}
                                />
                            </div>
                            <div className="flex items-center gap-3">
                                <PrimaryButton
                                    disabled={confirmForm.processing}
                                >
                                    確認して有効化
                                </PrimaryButton>
                                <DangerButton
                                    type="button"
                                    onClick={disable}
                                    disabled={disableForm.processing}
                                >
                                    キャンセル
                                </DangerButton>
                            </div>
                        </form>
                    </div>
                )}

                {enabled && (
                    <div className="space-y-4">
                        <p className="text-sm text-gray-700">
                            2FA は現在{' '}
                            <span className="font-semibold text-green-600">
                                有効
                            </span>{' '}
                            です。
                        </p>

                        {recoveryCodes && (
                            <div className="rounded border border-amber-300 bg-amber-50 p-4">
                                <p className="text-sm font-semibold text-amber-800">
                                    この復旧コードを安全な場所に保管してください（認証アプリを紛失した際に使用）。
                                    この画面を離れると二度と表示されません。
                                </p>
                                <ul className="mt-2 grid grid-cols-2 gap-x-6 font-mono text-sm text-amber-900">
                                    {recoveryCodes.map((c) => (
                                        <li key={c}>{c}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="flex flex-wrap items-center gap-3">
                            <form onSubmit={regenerate}>
                                <SecondaryButton
                                    disabled={regenerateForm.processing}
                                >
                                    復旧コードを再発行
                                </SecondaryButton>
                            </form>
                            <form onSubmit={disable}>
                                <DangerButton
                                    disabled={disableForm.processing}
                                >
                                    2FA を無効化
                                </DangerButton>
                            </form>
                        </div>
                        <p className="text-xs text-gray-500">
                            ※ 無効化時にはパスワードの再入力を求められます。
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}
