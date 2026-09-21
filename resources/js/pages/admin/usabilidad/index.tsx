import { BarChart } from '@/components/charts';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatShortDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { ClipboardCheck, Gauge, MessageSquareText, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Usabilidad', href: '/admin/usabilidad' },
];

interface Props {
    total: number;
    average: number | null;
    interpretation: string | null;
    referenceAverage: number;
    byRole: { role: string; label: string; total: number; average: number }[];
    items: { item: number; statement: string; contribution: number | null }[];
    distribution: { label: string; value: number }[];
    comments: { id: number; role: string; score: number; comments: string; createdAt: string }[];
}

export default function UsabilidadReporte({ total, average, interpretation, referenceAverage, byRole, items, distribution, comments }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reporte de usabilidad" />

            <div className="space-y-6">
                <PageHeader
                    title="Usabilidad (SUS)"
                    description="Resultados del System Usability Scale. El puntaje va de 0 a 100 y no es un porcentaje: se compara contra el promedio de referencia de la escala."
                    icon={ClipboardCheck}
                />

                {total === 0 ? (
                    <EmptyState
                        icon={ClipboardCheck}
                        title="Todavía nadie ha respondido"
                        description="Cuando el personal y los pacientes respondan el cuestionario, aquí aparecerá el puntaje y su desglose."
                    />
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <StatCard label="Puntaje SUS" value={average ?? '—'} hint={interpretation ?? undefined} icon={Gauge} tone="primary" />
                            <StatCard
                                label="Promedio de referencia"
                                value={referenceAverage}
                                hint={
                                    average !== null && average >= referenceAverage
                                        ? `${(average - referenceAverage).toFixed(1)} por encima`
                                        : average !== null
                                          ? `${(referenceAverage - average).toFixed(1)} por debajo`
                                          : undefined
                                }
                                icon={Gauge}
                                tone="info"
                            />
                            <StatCard label="Respuestas" value={total} icon={Users} tone="brand" />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Por rol</CardTitle>
                                    <p className="text-muted-foreground text-sm">Quién evaluó y con qué puntaje</p>
                                </CardHeader>
                                <CardContent>
                                    <ul className="divide-border/70 divide-y">
                                        {byRole.map((entry) => (
                                            <li key={entry.role} className="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-semibold">{entry.label}</p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {entry.total} {entry.total === 1 ? 'respuesta' : 'respuestas'}
                                                    </p>
                                                </div>
                                                <span className="tabular text-lg font-extrabold">{entry.average}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Distribución de puntajes</CardTitle>
                                    <p className="text-muted-foreground text-sm">Cuántas respuestas cayeron en cada rango</p>
                                </CardHeader>
                                <CardContent>
                                    <BarChart data={distribution} color="var(--chart-2)" />
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Aporte de cada afirmación</CardTitle>
                                <p className="text-muted-foreground text-sm">
                                    De 0 a 4. El puntaje total no dice qué arreglar; esta tabla sí: los aportes más bajos señalan dónde la plataforma
                                    estorba.
                                </p>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-3">
                                    {[...items]
                                        .sort((a, b) => (a.contribution ?? 0) - (b.contribution ?? 0))
                                        .map((entry) => {
                                            const contribution = entry.contribution ?? 0;
                                            const weak = contribution < 2;

                                            return (
                                                <li key={entry.item} className="flex items-center gap-3">
                                                    <span className="text-muted-foreground w-6 shrink-0 text-xs font-bold">{entry.item}.</span>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm">{entry.statement}</p>
                                                        <div className="bg-muted mt-1.5 h-1.5 overflow-hidden rounded-full">
                                                            <div
                                                                className={weak ? 'bg-warning h-full' : 'bg-primary h-full'}
                                                                style={{ width: `${(contribution / 4) * 100}%` }}
                                                            />
                                                        </div>
                                                    </div>
                                                    <span className="tabular w-10 shrink-0 text-right text-sm font-bold">
                                                        {contribution.toFixed(2)}
                                                    </span>
                                                </li>
                                            );
                                        })}
                                </ul>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-2.5">
                                    <span className="bg-primary-soft text-accent-foreground flex size-9 items-center justify-center rounded-lg">
                                        <MessageSquareText className="size-4.5" aria-hidden="true" />
                                    </span>
                                    <div>
                                        <CardTitle>Comentarios</CardTitle>
                                        <p className="text-muted-foreground mt-1 text-sm">Se muestran sin nombre, solo con el rol</p>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {comments.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">Nadie dejó comentarios todavía.</p>
                                ) : (
                                    <ul className="space-y-3">
                                        {comments.map((comment) => (
                                            <li key={comment.id} className="border-border/70 rounded-lg border p-4">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge variant="secondary">{comment.role}</Badge>
                                                    <span className="tabular text-sm font-bold">{comment.score}</span>
                                                    <span className="text-muted-foreground text-xs">{formatShortDate(comment.createdAt)}</span>
                                                </div>
                                                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{comment.comments}</p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
