import { useState } from 'react';

const SIZE_CLASSES = {
    xs: 'h-5 w-5 text-[10px]',
    sm: 'h-6 w-6 text-[11px]',
    md: 'h-8 w-8 text-xs',
    lg: 'h-10 w-10 text-sm',
};

export default function AssetIcon({ symbol, iconUrl, size = 'sm', className = '' }) {
    const [failed, setFailed] = useState(false);
    const sizeCls = SIZE_CLASSES[size] ?? SIZE_CLASSES.sm;
    const show = iconUrl && !failed;

    if (show) {
        return (
            <img
                src={iconUrl}
                alt={symbol}
                onError={() => setFailed(true)}
                className={`${sizeCls} shrink-0 rounded-full bg-white object-contain ring-1 ring-gray-200 ${className}`}
            />
        );
    }

    return (
        <span
            className={`${sizeCls} inline-flex shrink-0 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700 ${className}`}
        >
            {(symbol ?? '').slice(0, 2)}
        </span>
    );
}
