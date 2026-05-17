import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useRef, useState } from 'react';

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
    const pickerRef = useRef(null);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [searchQ, setSearchQ] = useState('');
    const [searchHits, setSearchHits] = useState([]);
    const [searchLoading, setSearchLoading] = useState(false);
    const [knownAssets, setKnownAssets] = useState(() =>
        Object.fromEntries((assets || []).map((a) => [a.id, a])),
    );

    useEffect(() => {
        setKnownAssets((prev) => {
            const next = { ...prev };
            (assets || []).forEach((a) => {
                next[a.id] = a;
            });
            return next;
        });
    }, [assets]);

    useEffect(() => {
        if (!pickerOpen) {
            return undefined;
        }
        const delay = searchQ === '' ? 0 : 280;
        const t = setTimeout(() => {
            setSearchLoading(true);
            axios
                .get(route('transactions.assets.search'), {
                    params: { q: searchQ, limit: 50 },
                })
                .then((res) => {
                    setSearchHits(res.data?.data ?? []);
                })
                .catch(() => setSearchHits([]))
                .finally(() => setSearchLoading(false));
        }, delay);
        return () => clearTimeout(t);
    }, [pickerOpen, searchQ]);

    useEffect(() => {
        if (!pickerOpen) {
            return undefined;
        }
        const onDoc = (e) => {
            if (pickerRef.current && !pickerRef.current.contains(e.target)) {
                setPickerOpen(false);
            }
        };
        document.addEventListener('mousedown', onDoc);
        return () => document.removeEventListener('mousedown', onDoc);
    }, [pickerOpen]);

    const selectedAsset = useMemo(() => {
        const id = Number(data.asset_id);
        if (!id) {
            return undefined;
        }
        return knownAssets[id];
    }, [knownAssets, data.asset_id]);

    const pickAsset = (row) => {
        setKnownAssets((prev) => ({ ...prev, [row.id]: row }));
        setData('asset_id', String(row.id));
        setPickerOpen(false);
    };

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

                <div className="relative sm:col-span-2" ref={pickerRef}>
                    <InputLabel htmlFor="asset_picker" value="銘柄" />
                    <button
                        id="asset_picker"
                        type="button"
                        onClick={() => {
                            setPickerOpen((o) => {
                                const next = !o;
                                if (next) {
                                    setSearchQ('');
                                }
                                return next;
                            });
                        }}
                        className={
                            'mt-1 flex w-full items-center justify-between rounded-md border bg-white px-3 py-2 text-left text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 ' +
                            (errors.asset_id ? 'border-red-300' : 'border-gray-300')
                        }
                    >
                        <span className={selectedAsset ? 'text-gray-900' : 'text-gray-500'}>
                            {selectedAsset
                                ? `${selectedAsset.symbol} — ${selectedAsset.name}`
                                : 'クリックして検索・選択'}
                        </span>
                        <span className="text-xs text-gray-400">{pickerOpen ? '▲' : '▼'}</span>
                    </button>
                    {pickerOpen && (
                        <div className="absolute z-30 mt-1 max-h-72 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg">
                            <div className="border-b border-gray-100 p-2">
                                <TextInput
                                    value={searchQ}
                                    onChange={(e) => setSearchQ(e.target.value)}
                                    placeholder="シンボル・銘柄名・CoinGecko ID"
                                    className="block w-full text-sm"
                                    autoComplete="off"
                                />
                            </div>
                            <div className="max-h-52 overflow-y-auto text-sm">
                                {searchLoading && (
                                    <div className="px-3 py-2 text-gray-500">読み込み中…</div>
                                )}
                                {!searchLoading && searchHits.length === 0 && (
                                    <div className="px-3 py-2 text-gray-500">該当がありません</div>
                                )}
                                {!searchLoading &&
                                    searchHits.map((a) => (
                                        <button
                                            key={a.id}
                                            type="button"
                                            onClick={() => pickAsset(a)}
                                            className="flex w-full flex-col items-start border-b border-gray-50 px-3 py-2 text-left hover:bg-indigo-50"
                                        >
                                            <span className="font-medium text-gray-900">
                                                {a.symbol}
                                                {a.coingecko_id ? (
                                                    <span className="ml-2 text-xs font-normal text-gray-500">
                                                        ({a.coingecko_id})
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span className="text-xs text-gray-600">{a.name}</span>
                                        </button>
                                    ))}
                            </div>
                        </div>
                    )}
                    <input type="hidden" name="asset_id" value={data.asset_id ?? ''} />
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
