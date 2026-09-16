import { LineChart } from '@/components/charts';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type KdigoVariant = 'success' | 'info' | 'warning' | 'destructive';

/** Categorías KDIGO: el deterioro de función renal crece con el número. */
export const kdigoVariants: Record<string, KdigoVariant> = {
    G1: 'success',
    G2: 'info',
    G3a: 'warning',
    G3b: 'warning',
    G4: 'destructive',
    G5: 'destructive',
};

export interface EgfrPoint {
    label: string;
    value: number;
    category: string | null;
    albuminuria: string | null;
}

export function EgfrSeries({ points }: { points: EgfrPoint[] }) {
    if (points.length === 0) {
        return null;
    }

    const ultimo = points[points.length - 1];

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between space-y-0">
                <div>
                    <CardTitle className="text-sm">Función renal (TFGe)</CardTitle>
                    <p className="tabular mt-1.5 text-2xl font-extrabold">
                        {ultimo.value}
                        <span className="text-muted-foreground ml-1 text-sm font-semibold">mL/min/1,73 m²</span>
                    </p>
                </div>
                {ultimo.category && (
                    <Badge variant={kdigoVariants[ultimo.category] ?? 'secondary'}>
                        {ultimo.category}
                        {ultimo.albuminuria ? ` · ${ultimo.albuminuria}` : ''}
                    </Badge>
                )}
            </CardHeader>
            <CardContent>
                {/* Sin banda de referencia, a diferencia de los signos vitales: en
                    la TFGe lo relevante no es caer fuera de un rango puntual sino
                    la pendiente entre controles, y esa lectura la hace el apoyo a
                    la decisión. El gráfico muestra la serie, no juzga. */}
                <LineChart data={points} unit="mL/min/1,73 m²" color="var(--chart-3)" height={180} />
                <p className="text-muted-foreground mt-2 text-center text-xs">
                    {points.length === 1
                        ? 'Un solo control registrado: hacen falta al menos dos para valorar la evolución.'
                        : `${points.length} controles, ordenados por fecha de laboratorio.`}
                </p>
            </CardContent>
        </Card>
    );
}
