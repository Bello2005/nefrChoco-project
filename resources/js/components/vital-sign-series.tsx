import { LineChart } from '@/components/charts';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export interface VitalSeries {
    type: string;
    label: string;
    shortLabel: string;
    unit: string;
    range: { min: number; max: number } | null;
    latest: number | null;
    status: string;
    points: { label: string; value: number }[];
}

const statusVariants: Record<string, 'success' | 'warning' | 'destructive'> = {
    normal: 'success',
    bajo: 'warning',
    alto: 'destructive',
};

const statusLabels: Record<string, string> = {
    normal: 'En rango',
    bajo: 'Bajo',
    alto: 'Alto',
};

const palette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

export function VitalSignSeries({ series }: { series: VitalSeries[] }) {
    return (
        // Una sola columna: estas tarjetas viven junto a un panel lateral, y a
        // dos columnas la gráfica queda demasiado estrecha para leer la tendencia.
        <div className="grid gap-4">
            {series.map((item, index) => (
                <Card key={item.type}>
                    <CardHeader className="flex-row items-start justify-between space-y-0">
                        <div>
                            <CardTitle className="text-sm">{item.label}</CardTitle>
                            <p className="tabular mt-1.5 text-2xl font-extrabold">
                                {item.latest ?? '—'}
                                <span className="text-muted-foreground ml-1 text-sm font-semibold">{item.unit}</span>
                            </p>
                        </div>
                        <Badge variant={statusVariants[item.status] ?? 'success'}>{statusLabels[item.status] ?? item.status}</Badge>
                    </CardHeader>
                    <CardContent>
                        <LineChart
                            data={item.points}
                            unit={item.unit}
                            color={palette[index % palette.length]}
                            referenceBand={item.range ?? undefined}
                            height={180}
                        />
                        {item.range && (
                            <p className="text-muted-foreground mt-2 text-center text-xs">
                                Franja verde: rango de referencia {item.range.min} – {item.range.max} {item.unit}
                            </p>
                        )}
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
