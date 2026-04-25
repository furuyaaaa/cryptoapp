import AssetIcon from '@/Components/AssetIcon';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';

export default function AssetForm({
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
            <div className="flex items-center gap-4 rounded-md border border-gray-200 bg-gray-50 p-4">
                <AssetIcon symbol={data.symbol} iconUrl={data.icon_url} size="lg" />
                <div className="min-w-0">
                    <div className="text-sm font-semibold text-gray-900">
                        {data.symbol || 'SYMBOL'}
                    </div>
                    <div className="truncate text-xs text-gray-500">
                        {data.name || '銘柄名'}
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="symbol" value="シンボル" />
                    <TextInput
                        id="symbol"
                        type="text"
                        value={data.symbol}
                        onChange={(e) =>
                            setData('symbol', e.target.value.toUpperCase())
                        }
                        className="mt-1 block w-full font-mono uppercase"
                        placeholder="BTC"
                        maxLength={20}
                        required
                        autoFocus
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        英大文字と数字のみ。例: BTC, ETH, USDT
                    </p>
                    <InputError className="mt-2" message={errors.symbol} />
                </div>

                <div>
                    <InputLabel htmlFor="name" value="銘柄名" />
                    <TextInput
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="mt-1 block w-full"
                        placeholder="Bitcoin"
                        maxLength={100}
                        required
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="coingecko_id" value="CoinGecko ID (任意)" />
                <TextInput
                    id="coingecko_id"
                    type="text"
                    value={data.coingecko_id ?? ''}
                    onChange={(e) =>
                        setData('coingecko_id', e.target.value.toLowerCase())
                    }
                    className="mt-1 block w-full font-mono lowercase"
                    placeholder="bitcoin"
                    maxLength={100}
                />
                <p className="mt-1 text-xs text-gray-500">
                    <a
                        href="https://www.coingecko.com/"
                        target="_blank"
                        rel="noreferrer"
                        className="text-indigo-600 hover:text-indigo-800"
                    >
                        CoinGecko
                    </a>
                    の各銘柄ページ URL 末尾 (例:{' '}
                    <span className="font-mono">bitcoin</span>)。価格取得に使用します。
                </p>
                <InputError className="mt-2" message={errors.coingecko_id} />
            </div>

            <div>
                <InputLabel htmlFor="icon_url" value="アイコン URL (任意)" />
                <TextInput
                    id="icon_url"
                    type="url"
                    value={data.icon_url ?? ''}
                    onChange={(e) => setData('icon_url', e.target.value)}
                    className="mt-1 block w-full"
                    placeholder="https://..."
                    maxLength={500}
                />
                <p className="mt-1 text-xs text-gray-500">
                    価格更新時に CoinGecko から自動取得されますが、ここで手動指定もできます。
                </p>
                <InputError className="mt-2" message={errors.icon_url} />
            </div>

            <div className="flex items-center justify-end gap-3">
                <Link href={route('assets.index')}>
                    <SecondaryButton type="button">キャンセル</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
