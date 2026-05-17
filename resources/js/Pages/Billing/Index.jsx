import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, router, usePage } from '@inertiajs/react';

export default function Index({
    subscribed,
    priceConfigured,
    hasStripeCustomer,
    checkout,
    sessionId,
    subscriptionRequired,
}) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    課金・プラン
                </h2>
            }
        >
            <Head title="課金・プラン" />

            <div className="py-12">
                <div className="mx-auto max-w-lg sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <p className="mb-2 text-sm text-gray-600">
                                ポートフォリオ・取引履歴などの機能をご利用になるには、月額プランへのお申し込みが必要です。お支払いは
                                Stripe（カード）で処理されます。
                            </p>

                            {checkout === 'cancel' && (
                                <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    お申し込みをキャンセルしました。
                                </div>
                            )}

                            {sessionId && (
                                <>
                                    {subscribed ? (
                                        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                                            お申し込みありがとうございます。プランが有効になりました。
                                        </div>
                                    ) : (
                                        <div className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                            決済を受け付けました。Stripe
                                            からの通知反映まで数秒かかることがあります。このページを再読み込みしてください。
                                        </div>
                                    )}
                                </>
                            )}

                            {flash?.error && (
                                <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                    {flash.error}
                                </div>
                            )}

                            <div className="mt-6 space-y-4 border-t border-gray-100 pt-6">
                                {subscribed ? (
                                    <>
                                        <p className="text-sm text-gray-700">
                                            現在、有料プランが有効です。
                                        </p>
                                        {hasStripeCustomer && (
                                            <PrimaryButton
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route('billing.portal'),
                                                    )
                                                }
                                            >
                                                お支払い・プランの管理（Stripe）
                                            </PrimaryButton>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <p className="text-sm text-gray-700">
                                            プランに未加入のため、ダッシュボード等へは進めません。
                                        </p>
                                        {!priceConfigured && (
                                            <div className="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                                現在、新規のお申し込みを受け付けておりません。しばらく経ってから再度お試しください。お急ぎの場合はサポートまでお問い合わせください。
                                            </div>
                                        )}
                                        {priceConfigured && (
                                            <PrimaryButton
                                                type="button"
                                                className="bg-emerald-600 hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-800"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'billing.checkout',
                                                        ),
                                                    )
                                                }
                                            >
                                                プランに申し込む（Stripe
                                                Checkout）
                                            </PrimaryButton>
                                        )}
                                    </>
                                )}
                            </div>

                            {!subscriptionRequired && (
                                <p className="mt-6 border-t border-gray-100 pt-4 text-xs text-gray-500">
                                    開発モード:{' '}
                                    <code>SUBSCRIPTION_REQUIRED=false</code>{' '}
                                    のため課金チェックは無効です。
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
