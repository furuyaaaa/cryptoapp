export default function ChangeBadge({ value, className = '' }) {
    if (value === null || value === undefined) {
        return <span className={`text-xs text-gray-400 ${className}`}>—</span>;
    }

    const pct = value * 100;
    const isPositive = value >= 0;
    const colorCls = isPositive
        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
        : 'bg-rose-50 text-rose-700 ring-rose-200';
    const formatted = `${isPositive ? '+' : ''}${pct.toLocaleString('ja-JP', {
        maximumFractionDigits: 2,
        minimumFractionDigits: 2,
    })}%`;

    return (
        <span
            className={`inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium ring-1 ring-inset ${colorCls} ${className}`}
        >
            {formatted}
        </span>
    );
}
