import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';
import { useMemo } from 'react';

const jpy = (v) =>
    new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
        maximumFractionDigits: 0,
    }).format(Math.round(v || 0));

function SelectInput({ id, value, onChange, children, className = '' }) {
    return (
        <select
            id={id}
            name={id}
            value={value ?? ''}
            onChange={onChange}
            className={
                'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 ' +
                className
            }
        >
            {children}
        </select>
    );
}

export default function TransactionForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
    portfolios,
    assets,
    exchanges,
    types,
    cancelHref,
}) {
    const selectedAsset = useMemo(
        () => assets.find((a) => a.id === Number(data.asset_id)),
        [assets, data.asset_id],
    );

    const estimatedTotal = useMemo(() => {
        const amt = parseFloat(data.amount) || 0;
        const price = parseFloat(data.price_jpy) || 0;
        const fee = parseFloat(data.fee_jpy) || 0;
        const base = amt * price;
        return data.type === 'buy' ? base + fee : base - fee;
    }, [data.amount, data.price_jpy, data.fee_jpy, data.type]);

    const useCurrentPrice = () => {
        if (selectedAsset?.current_price_jpy) {
            setData('price_jpy', String(selectedAsset.current_price_jpy));
        }
    };

    return (
        <form
            onSubmit={onSubmit}
            className="space-y-6 rounded-lg bg-white p-6 shadow sm:p-8"
        >
            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="portfolio_id" value="ポートフォリオ" />
                    <SelectInput
                        id="portfolio_id"
                        value={data.portfolio_id}
                        onChange={(e) => setData('portfolio_id', e.target.value)}
                        className="mt-1"
                    >
                        {portfolios.map((p) => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </SelectInput>
                    <InputError className="mt-2" message={errors.portfolio_id} />
                </div>

                <div>
                    <InputLabel htmlFor="type" value="取引種別" />
                    <SelectInput
                        id="type"
                        value={data.type}
                        onChange={(e) => setData('type', e.target.value)}
                        className="mt-1"
                    >
                        {types.map((t) => (
                            <option key={t.value} value={t.value}>{t.label}</option>
                        ))}
                    </SelectInput>
                    <InputError className="mt-2" message={errors.type} />
                </div>

                <div>
                    <InputLabel htmlFor="asset_id" value="銘柄" />
                    <SelectInput
                        id="asset_id"
                        value={data.asset_id}
                        onChange={(e) => setData('asset_id', e.target.value)}
                        className="mt-1"
                    >
                        {assets.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.symbol} - {a.name}
                            </option>
                        ))}
                    </SelectInput>
                    <InputError className="mt-2" message={errors.asset_id} />
                </div>

                <div>
                    <InputLabel htmlFor="exchange_id" value="取引所 (任意)" />
                    <SelectInput
                        id="exchange_id"
                        value={data.exchange_id}
                        onChange={(e) => setData('exchange_id', e.target.value)}
                        className="mt-1"
                    >
                        <option value="">- 選択しない -</option>
                        {exchanges.map((e) => (
                            <option key={e.id} value={e.id}>{e.name}</option>
                        ))}
                    </SelectInput>
                    <InputError className="mt-2" message={errors.exchange_id} />
                </div>

                <div>
                    <InputLabel htmlFor="amount" value="数量" />
                    <TextInput
                        id="amount"
                        type="number"
                        step="any"
                        min="0"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        className="mt-1 block w-full"
                        placeholder="例: 0.1"
                        required
                    />
                    <InputError className="mt-2" message={errors.amount} />
                </div>

                <div>
                    <div className="flex items-center justify-between">
                        <InputLabel htmlFor="price_jpy" value="取引単価 (JPY)" />
                        {selectedAsset?.current_price_jpy > 0 && (
                            <button
                                type="button"
                                onClick={useCurrentPrice}
                                className="text-xs text-indigo-600 hover:text-indigo-800"
                            >
                                現在価格を使う ({jpy(selectedAsset.current_price_jpy)})
                            </button>
                        )}
                    </div>
                    <TextInput
                        id="price_jpy"
                        type="number"
                        step="any"
                        min="0"
                        value={data.price_jpy}
                        onChange={(e) => setData('price_jpy', e.target.value)}
                        className="mt-1 block w-full"
                        placeholder="例: 5000000"
                        required
                    />
                    <InputError className="mt-2" message={errors.price_jpy} />
                </div>

                <div>
                    <InputLabel htmlFor="fee_jpy" value="手数料 (JPY)" />
                    <TextInput
                        id="fee_jpy"
                        type="number"
                        step="any"
                        min="0"
                        value={data.fee_jpy ?? ''}
                        onChange={(e) => setData('fee_jpy', e.target.value)}
                        className="mt-1 block w-full"
                        placeholder="省略可 (既定: 0)"
                    />
                    <InputError className="mt-2" message={errors.fee_jpy} />
                </div>

                <div>
                    <InputLabel htmlFor="executed_at" value="取引日時" />
                    <TextInput
                        id="executed_at"
                        type="datetime-local"
                        value={data.executed_at}
                        onChange={(e) => setData('executed_at', e.target.value)}
                        className="mt-1 block w-full"
                        required
                    />
                    <InputError className="mt-2" message={errors.executed_at} />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="note" value="メモ (任意)" />
                <textarea
                    id="note"
                    value={data.note ?? ''}
                    onChange={(e) => setData('note', e.target.value)}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={2}
                    maxLength={1000}
                />
                <InputError className="mt-2" message={errors.note} />
            </div>

            {estimatedTotal > 0 && (
                <div className="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-700">
                    想定{data.type === 'buy' ? '取得原価' : data.type === 'sell' ? '受取金額' : '取引額'}: <span className="font-semibold">{jpy(estimatedTotal)}</span>
                </div>
            )}

            <div className="flex items-center justify-end gap-3">
                <Link href={cancelHref}>
                    <SecondaryButton type="button">キャンセル</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
