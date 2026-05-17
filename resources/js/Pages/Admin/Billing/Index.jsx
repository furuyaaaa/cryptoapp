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
                    課金・プラン（管理者）
                </h2>
            }
        >
            <Head title="課金・プラン（管理者）" />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <p className="mb-2 text-sm text-gray-600">
                                管理者向けの課金・運用画面です。一般ユーザー向けの
                                <code className="mx-0.5 rounded bg-gray-100 px-1 text-xs">
                                    /billing
                                </code>
                                とは別 URL です。本アプリの利用には有料プランが必要な場合、ご自身のアカウントでも
                                Stripe からお申し込みいただけます。
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
                                            プランに未加入のため、ダッシュボード・銘柄管理などへは進めません。
                                        </p>

                                        <div className="rounded-md border border-indigo-100 bg-indigo-50/80 px-4 py-3 text-sm text-gray-800">
                                            <p className="mb-2 font-medium text-indigo-900">
                                                運用チェックリスト
                                            </p>
                                            <ul className="list-inside list-disc space-y-1 text-gray-700">
                                                <li>
                                                    <code className="text-xs">
                                                        .env
                                                    </code>{' '}
                                                    に{' '}
                                                    <code className="text-xs">
                                                        STRIPE_KEY
                                                    </code>
                                                    ・
                                                    <code className="text-xs">
                                                        STRIPE_SECRET
                                                    </code>
                                                    ・
                                                    <code className="text-xs">
                                                        STRIPE_WEBHOOK_SECRET
                                                    </code>{' '}
                                                    （本番）を設定
                                                </li>
                                                <li>
                                                    定期課金用の{' '}
                                                    <code className="text-xs">
                                                        STRIPE_PRICE_ID
                                                    </code>{' '}
                                                    （
                                                    <code className="text-xs">
                                                        price_...
                                                    </code>
                                                    ）を Stripe ダッシュボードから取得して設定
                                                </li>
                                                <li>
                                                    Webhook エンドポイント（例:{' '}
                                                    <code className="break-all text-xs">
                                                        {typeof window !==
                                                        'undefined'
                                                            ? `${window.location.origin}/stripe/webhook`
                                                            : '/stripe/webhook'}
                                                    </code>
                                                    ）を Stripe に登録
                                                </li>
                                            </ul>
                                            <p className="mt-3">
                                                <a
                                                    href="https://dashboard.stripe.com/"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                                                >
                                                    Stripe ダッシュボードを開く
                                                </a>
                                            </p>
                                        </div>

                                        {!priceConfigured && (
                                            <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                                                <strong>要設定:</strong>{' '}
                                                <code className="text-xs">
                                                    STRIPE_PRICE_ID
                                                </code>{' '}
                                                が未設定です。上記の Price
                                                ID を .env に追加してください。
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
