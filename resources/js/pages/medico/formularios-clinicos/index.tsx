import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Formularios clínicos', href: '/medico/formularios-clinicos' },
];

type BadgeVariant = 'success' | 'info' | 'warning' | 'destructive' | 'secondary';

const riskVariants: Record<string, BadgeVariant> = {
    bajo: 'success',
    adherente: 'success',
    ligero: 'info',
    moderado: 'warning',
    parcial: 'warning',
    alto: 'destructive',
    no_adherente: 'destructive',
};

const riskLabels: Record<string, string> = {
    bajo: 'Riesgo bajo',
    ligero: 'Riesgo ligero',
    moderado: 'Riesgo moderado',
    alto: 'Riesgo alto',
    adherente: 'Adherente',
    parcial: 'Adherencia parcial',
    no_adherente: 'No adherente',
};

interface FormRow {
    id: number;
    patient: string | null;
    patientId: number;
    formType: string;
    templateName: string;
    score: number | null;
    riskLevel: string | null;
    recordedBy: string | null;
    createdAt: string;
}

interface Template {
    key: string;
    name: string;
    description: string;
}

interface Props {
    forms: FormRow[];
    templates: Template[];
    filters: { tipo: string };
}

export default function FormulariosIndex({ forms, templates, filters }: Props) {
    const applyFilter = (tipo: string) => {
        router.get(route('medico.formularios-clinicos.index'), tipo ? { tipo } : {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Formularios clínicos" />

            <div className="space-y-6">
                <PageHeader
                    title="Formularios clínicos"
                    description="Instrumentos de tamizaje y seguimiento con interpretación automática del resultado."
                    icon={ClipboardList}
                    actions={
                        <Button asChild>
                            <Link href={route('medico.formularios-clinicos.create')}>
                                <Plus />
                                Aplicar formulario
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    {templates.map((template) => (
                        <Link
                            key={template.key}
                            href={route('medico.formularios-clinicos.create', { tipo: template.key })}
                            className="bg-card border-border/70 hover:border-ring/40 group rounded-xl border p-5 shadow-sm transition-all duration-300 hover:shadow-md"
                        >
                            <span className="bg-primary-soft text-accent-foreground flex size-10 items-center justify-center rounded-lg transition-transform duration-300 group-hover:scale-105">
                                <ClipboardList className="size-5" />
                            </span>
                            <h3 className="font-display mt-3.5 text-sm font-bold">{template.name}</h3>
                            <p className="text-muted-foreground mt-1.5 line-clamp-3 text-xs">{template.description}</p>
                        </Link>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button size="sm" variant={filters.tipo === '' ? 'default' : 'outline'} onClick={() => applyFilter('')}>
                        Todos
                    </Button>
                    {templates.map((template) => (
                        <Button
                            key={template.key}
                            size="sm"
                            variant={filters.tipo === template.key ? 'default' : 'outline'}
                            onClick={() => applyFilter(template.key)}
                        >
                            {template.name}
                        </Button>
                    ))}
                </div>

                {forms.length === 0 ? (
                    <EmptyState
                        icon={ClipboardList}
                        title="Aún no has aplicado formularios"
                        description="Selecciona un instrumento arriba para aplicarlo a un paciente y obtener su interpretación."
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Paciente</TableHead>
                                <TableHead>Instrumento</TableHead>
                                <TableHead>Resultado</TableHead>
                                <TableHead>Aplicado por</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {forms.map((form) => (
                                <TableRow key={form.id}>
                                    <TableCell className="font-semibold">{form.patient}</TableCell>
                                    <TableCell className="text-muted-foreground text-sm">{form.templateName}</TableCell>
                                    <TableCell>
                                        {form.riskLevel ? (
                                            <Badge variant={riskVariants[form.riskLevel] ?? 'secondary'}>
                                                {riskLabels[form.riskLevel] ?? form.riskLevel}
                                                {form.score !== null && <span className="tabular">· {form.score}</span>}
                                            </Badge>
                                        ) : (
                                            <span className="text-muted-foreground text-sm">Sin puntaje</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">{form.recordedBy ?? '—'}</TableCell>
                                    <TableCell className="text-muted-foreground text-sm">{formatDateTime(form.createdAt)}</TableCell>
                                    <TableCell className="text-right">
                                        <Button size="sm" variant="outline" asChild>
                                            <Link href={route('medico.formularios-clinicos.show', form.id)}>Ver</Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </AppLayout>
    );
}
