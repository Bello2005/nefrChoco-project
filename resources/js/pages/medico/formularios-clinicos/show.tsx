import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ClipboardList, Lightbulb, UserRound } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Formularios clínicos', href: '/medico/formularios-clinicos' },
    { title: 'Detalle', href: '#' },
];

const toneStyles: Record<string, string> = {
    success: 'border-success/30 bg-success-soft text-success',
    info: 'border-info/30 bg-info-soft text-info',
    warning: 'border-warning/30 bg-warning-soft text-warning',
    destructive: 'border-destructive/30 bg-destructive-soft text-destructive',
};

interface Props {
    form: {
        id: number;
        templateName: string;
        formType: string;
        patient: { id: number; full_name: string; municipality: string };
        recordedBy: string | null;
        createdAt: string;
        answers: { label: string; value: string }[];
    };
    score: { total: number; max: number; level: string; label: string; tone: string; advice: string } | null;
}

export default function FormularioShow({ form, score }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={form.templateName} />

            <div className="space-y-6">
                <PageHeader
                    title={form.templateName}
                    description={`Aplicado el ${formatDateTime(form.createdAt)}${form.recordedBy ? ` por ${form.recordedBy}` : ''}`}
                    icon={ClipboardList}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={route('medico.pacientes.show', form.patient.id)}>
                                <UserRound />
                                Ver paciente
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Respuestas</CardTitle>
                            <p className="text-muted-foreground text-sm">
                                {form.patient.full_name} · {form.patient.municipality}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <dl className="divide-border/70 divide-y">
                                {form.answers.map((answer) => (
                                    <div key={answer.label} className="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-5 sm:gap-4">
                                        <dt className="text-muted-foreground text-sm sm:col-span-3">{answer.label}</dt>
                                        <dd className="text-sm font-semibold sm:col-span-2">{answer.value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        {score ? (
                            <div className={cn('rounded-xl border p-5', toneStyles[score.tone] ?? 'border-border bg-muted/40')}>
                                <p className="text-xs font-bold tracking-wide uppercase">Resultado</p>
                                <p className="tabular mt-3 text-4xl font-extrabold">
                                    {score.total}
                                    <span className="text-lg font-bold opacity-60">/{score.max}</span>
                                </p>
                                <p className="mt-1 text-sm font-bold">{score.label}</p>

                                <div className="mt-4 flex gap-2.5 border-t border-current/15 pt-4">
                                    <Lightbulb className="mt-0.5 size-4 shrink-0" />
                                    <p className="text-xs leading-relaxed opacity-90">{score.advice}</p>
                                </div>
                            </div>
                        ) : (
                            <div className="border-border bg-muted/40 rounded-xl border p-5">
                                <p className="text-muted-foreground text-xs font-bold tracking-wide uppercase">Resultado</p>
                                <p className="mt-2 text-sm">Este instrumento es de seguimiento clínico y no genera un puntaje automático.</p>
                            </div>
                        )}

                        <Button variant="outline" className="w-full" asChild>
                            <Link href={route('medico.formularios-clinicos.create', { paciente: form.patient.id, tipo: form.formType })}>
                                Aplicar de nuevo
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
