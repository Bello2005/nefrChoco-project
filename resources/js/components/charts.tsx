import { cn } from '@/lib/utils';
import { useMemo, useRef, useState } from 'react';

export interface ChartPoint {
    label: string;
    value: number;
}

interface Coord {
    x: number;
    y: number;
}

/** Catmull-Rom convertido a curvas Bézier: líneas suaves sin librerías externas. */
function buildSmoothPath(points: Coord[]): string {
    if (points.length === 0) return '';
    if (points.length === 1) return `M ${points[0].x} ${points[0].y}`;

    let path = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const previous = points[i - 1] ?? points[i];
        const current = points[i];
        const next = points[i + 1];
        const afterNext = points[i + 2] ?? next;

        const control1 = { x: current.x + (next.x - previous.x) / 6, y: current.y + (next.y - previous.y) / 6 };
        const control2 = { x: next.x - (afterNext.x - current.x) / 6, y: next.y - (afterNext.y - current.y) / 6 };

        path += ` C ${control1.x.toFixed(2)} ${control1.y.toFixed(2)}, ${control2.x.toFixed(2)} ${control2.y.toFixed(2)}, ${next.x.toFixed(2)} ${next.y.toFixed(2)}`;
    }

    return path;
}

interface LineChartProps {
    data: ChartPoint[];
    color?: string;
    unit?: string;
    height?: number;
    /** Franja de referencia clínica (p. ej. rango normal de un signo vital). */
    referenceBand?: { min: number; max: number };
    className?: string;
}

export function LineChart({ data, color = 'var(--chart-1)', unit = '', height = 220, referenceBand, className }: LineChartProps) {
    const [hoverIndex, setHoverIndex] = useState<number | null>(null);
    const svgRef = useRef<SVGSVGElement>(null);
    const gradientId = useMemo(() => `area-${Math.random().toString(36).slice(2, 9)}`, []);

    const width = 640;
    const padding = { top: 18, right: 16, bottom: 30, left: 44 };
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;

    const { coords, min, max } = useMemo(() => {
        const values = data.map((d) => d.value);
        const rawMin = Math.min(...values, referenceBand?.min ?? Infinity);
        const rawMax = Math.max(...values, referenceBand?.max ?? -Infinity);
        const span = rawMax - rawMin || 1;
        const lower = rawMin - span * 0.15;
        const upper = rawMax + span * 0.15;

        const mapped = data.map((point, index) => ({
            x: padding.left + (data.length === 1 ? plotWidth / 2 : (index / (data.length - 1)) * plotWidth),
            y: padding.top + plotHeight - ((point.value - lower) / (upper - lower)) * plotHeight,
        }));

        return { coords: mapped, min: lower, max: upper };
    }, [data, referenceBand, plotWidth, plotHeight, padding.left, padding.top]);

    if (data.length === 0) {
        return null;
    }

    const linePath = buildSmoothPath(coords);
    const areaPath = `${linePath} L ${coords[coords.length - 1].x} ${padding.top + plotHeight} L ${coords[0].x} ${padding.top + plotHeight} Z`;

    const gridValues = [0, 0.25, 0.5, 0.75, 1].map((ratio) => min + (max - min) * ratio);

    const bandTop = referenceBand ? padding.top + plotHeight - ((referenceBand.max - min) / (max - min)) * plotHeight : 0;
    const bandBottom = referenceBand ? padding.top + plotHeight - ((referenceBand.min - min) / (max - min)) * plotHeight : 0;

    const handleMove = (event: React.MouseEvent<SVGSVGElement>) => {
        const rect = svgRef.current?.getBoundingClientRect();
        if (!rect) return;

        const relativeX = ((event.clientX - rect.left) / rect.width) * width;
        let closest = 0;
        let smallestDistance = Infinity;

        coords.forEach((coord, index) => {
            const distance = Math.abs(coord.x - relativeX);
            if (distance < smallestDistance) {
                smallestDistance = distance;
                closest = index;
            }
        });

        setHoverIndex(closest);
    };

    const active = hoverIndex !== null ? { point: data[hoverIndex], coord: coords[hoverIndex] } : null;

    return (
        <div className={cn('relative w-full', className)}>
            <svg
                ref={svgRef}
                viewBox={`0 0 ${width} ${height}`}
                className="h-auto w-full touch-none"
                onMouseMove={handleMove}
                onMouseLeave={() => setHoverIndex(null)}
                role="img"
                aria-label="Gráfico de evolución"
            >
                <defs>
                    <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={color} stopOpacity="0.22" />
                        <stop offset="100%" stopColor={color} stopOpacity="0" />
                    </linearGradient>
                </defs>

                {referenceBand && (
                    <rect
                        x={padding.left}
                        y={bandTop}
                        width={plotWidth}
                        height={Math.max(bandBottom - bandTop, 0)}
                        fill="var(--success)"
                        opacity="0.09"
                        rx="6"
                    />
                )}

                {gridValues.map((value, index) => {
                    const y = padding.top + plotHeight - ((value - min) / (max - min)) * plotHeight;
                    return (
                        <g key={index}>
                            <line
                                x1={padding.left}
                                y1={y}
                                x2={width - padding.right}
                                y2={y}
                                stroke="var(--border)"
                                strokeWidth="1"
                                strokeDasharray={index === 0 ? undefined : '3 5'}
                                vectorEffect="non-scaling-stroke"
                            />
                            <text x={padding.left - 8} y={y + 4} textAnchor="end" className="fill-muted-foreground text-[11px]">
                                {Math.round(value)}
                            </text>
                        </g>
                    );
                })}

                <path d={areaPath} fill={`url(#${gradientId})`} />
                <path
                    d={linePath}
                    fill="none"
                    stroke={color}
                    strokeWidth="2.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    vectorEffect="non-scaling-stroke"
                />

                {coords.map((coord, index) => (
                    <circle
                        key={index}
                        cx={coord.x}
                        cy={coord.y}
                        r={hoverIndex === index ? 5.5 : 3.5}
                        fill="var(--card)"
                        stroke={color}
                        strokeWidth="2.5"
                        vectorEffect="non-scaling-stroke"
                        className="transition-[r] duration-150"
                    />
                ))}

                {active && (
                    <line
                        x1={active.coord.x}
                        y1={padding.top}
                        x2={active.coord.x}
                        y2={padding.top + plotHeight}
                        stroke={color}
                        strokeWidth="1"
                        strokeDasharray="3 4"
                        opacity="0.5"
                        vectorEffect="non-scaling-stroke"
                    />
                )}

                {data.map((point, index) => {
                    const showLabel = data.length <= 7 || index % Math.ceil(data.length / 6) === 0;
                    if (!showLabel) return null;

                    return (
                        <text key={index} x={coords[index].x} y={height - 8} textAnchor="middle" className="fill-muted-foreground text-[11px]">
                            {point.label}
                        </text>
                    );
                })}
            </svg>

            {active && (
                <div
                    className="bg-popover border-border pointer-events-none absolute -translate-x-1/2 -translate-y-full rounded-lg border px-2.5 py-1.5 shadow-md"
                    style={{ left: `${(active.coord.x / width) * 100}%`, top: `${(active.coord.y / height) * 100}%` }}
                >
                    <p className="text-muted-foreground text-[11px] font-medium">{active.point.label}</p>
                    <p className="tabular text-sm font-bold">
                        {active.point.value}
                        {unit && <span className="text-muted-foreground ml-0.5 text-xs font-medium">{unit}</span>}
                    </p>
                </div>
            )}
        </div>
    );
}

interface BarChartProps {
    data: ChartPoint[];
    color?: string;
    height?: number;
    className?: string;
}

export function BarChart({ data, color = 'var(--chart-1)', height = 230, className }: BarChartProps) {
    const width = 460;
    const padding = { top: 18, right: 12, bottom: 32, left: 34 };
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const max = Math.max(...data.map((d) => d.value), 1);
    const slot = plotWidth / data.length;
    const barWidth = Math.min(slot * 0.58, 46);

    return (
        <svg viewBox={`0 0 ${width} ${height}`} className={cn('h-auto w-full', className)} role="img" aria-label="Gráfico de barras">
            {[0, 0.5, 1].map((ratio, index) => {
                const y = padding.top + plotHeight - ratio * plotHeight;
                return (
                    <g key={index}>
                        <line
                            x1={padding.left}
                            y1={y}
                            x2={width - padding.right}
                            y2={y}
                            stroke="var(--border)"
                            strokeDasharray={ratio === 0 ? undefined : '3 5'}
                            vectorEffect="non-scaling-stroke"
                        />
                        <text x={padding.left - 8} y={y + 4} textAnchor="end" className="fill-muted-foreground text-[11px]">
                            {Math.round(max * ratio)}
                        </text>
                    </g>
                );
            })}

            {data.map((point, index) => {
                const barHeight = (point.value / max) * plotHeight;
                const x = padding.left + slot * index + (slot - barWidth) / 2;
                const y = padding.top + plotHeight - barHeight;

                return (
                    <g key={point.label}>
                        <rect x={x} y={y} width={barWidth} height={Math.max(barHeight, 2)} rx="6" fill={color} opacity={0.9} />
                        <text x={x + barWidth / 2} y={y - 6} textAnchor="middle" className="fill-foreground text-[11px] font-semibold">
                            {point.value}
                        </text>
                        <text x={x + barWidth / 2} y={height - 10} textAnchor="middle" className="fill-muted-foreground text-[11px]">
                            {point.label}
                        </text>
                    </g>
                );
            })}
        </svg>
    );
}

interface DonutSegment {
    label: string;
    value: number;
    color: string;
}

export function DonutChart({ segments, total, caption }: { segments: DonutSegment[]; total: number; caption?: string }) {
    const size = 156;
    const strokeWidth = 20;
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const safeTotal = total || 1;

    let offset = 0;

    return (
        // Siempre en columna: la tarjeta que la contiene suele ser estrecha y
        // un layout horizontal recortaría las etiquetas de los diagnósticos.
        <div className="flex w-full flex-col items-center gap-5">
            <div className="relative shrink-0">
                <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90">
                    <circle cx={size / 2} cy={size / 2} r={radius} fill="none" stroke="var(--muted)" strokeWidth={strokeWidth} />
                    {segments.map((segment) => {
                        const length = (segment.value / safeTotal) * circumference;
                        const element = (
                            <circle
                                key={segment.label}
                                cx={size / 2}
                                cy={size / 2}
                                r={radius}
                                fill="none"
                                stroke={segment.color}
                                strokeWidth={strokeWidth}
                                strokeDasharray={`${length} ${circumference - length}`}
                                strokeDashoffset={-offset}
                                strokeLinecap="round"
                            />
                        );
                        offset += length;
                        return element;
                    })}
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center">
                    <span className="tabular text-3xl font-extrabold">{total}</span>
                    {caption && <span className="text-muted-foreground text-xs">{caption}</span>}
                </div>
            </div>

            <ul className="w-full min-w-0 flex-1 space-y-2.5">
                {segments.map((segment) => (
                    <li key={segment.label} className="flex items-center justify-between gap-3 text-sm">
                        <span className="flex min-w-0 items-center gap-2.5">
                            <span className="size-2.5 shrink-0 rounded-full" style={{ background: segment.color }} />
                            <span className="text-muted-foreground truncate" title={segment.label}>
                                {segment.label}
                            </span>
                        </span>
                        <span className="tabular shrink-0 font-semibold">{segment.value}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
