export default function Sparkline({ data = [], positive, width = 100, height = 28, className = '' }) {
    if (!data || data.length < 2) {
        return <div className={`text-xs text-gray-300 ${className}`}>—</div>;
    }

    const min = Math.min(...data);
    const max = Math.max(...data);
    const range = max - min || 1;
    const stepX = width / (data.length - 1);

    const points = data
        .map((v, i) => {
            const x = i * stepX;
            const y = height - ((v - min) / range) * height;
            return `${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');

    const isPositive = positive ?? data[data.length - 1] >= data[0];
    const stroke = isPositive ? '#10b981' : '#ef4444';
    const fillId = isPositive ? 'spark-pos' : 'spark-neg';

    return (
        <svg width={width} height={height} className={className} viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none">
            <defs>
                <linearGradient id={fillId} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={stroke} stopOpacity="0.22" />
                    <stop offset="100%" stopColor={stroke} stopOpacity="0" />
                </linearGradient>
            </defs>
            <polygon
                points={`0,${height} ${points} ${width},${height}`}
                fill={`url(#${fillId})`}
            />
            <polyline
                points={points}
                fill="none"
                stroke={stroke}
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
