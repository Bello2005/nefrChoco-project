import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, PencilLine, ScrollText, ShieldCheck } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Auditoría', href: '/admin/auditoria' },
];

const subjectLabels: Record<string, string> = {
    Patient: 'Paciente',
    ClinicalHistory: 'Historia clínica',
    VitalSign: 'Signo vital',
    ClinicalForm: 'Formulario clínico',
};

const eventLabels: Record<string, string> = {
    created: 'Creación',
    updated: 'Actualización',
    deleted: 'Eliminación',
    consultado: 'Consulta',
};

interface ActivityRow {
    id: number;
    description: string;
    event: string | null;
    logName: string;
    subject: string;
    subjectId: number | null;
    causer: string;
    properties: Record<string, unknown>;
    createdAt: string;
}

interface Props {
    activities: {
        data: ActivityRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { tipo: string };
}

export default function AuditoriaIndex({ activities, filters }: Props) {
    const applyFilter = (tipo: string) => {
        router.get(route('admin.auditoria.index'), tipo ? { tipo } : {}, { preserveState: true, replace: true });
    };

    const tabs = [
        { key: '', label: 'Todo' },
        { key: 'accesos', label: 'Accesos' },
        { key: 'cambios', label: 'Cambios' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Auditoría" />

            <div className="space-y-6">
                <PageHeader
                    title="Auditoría de datos clínicos"
                    description="Registro de quién consultó o modificó información de pacientes, conforme a la Ley 1581 de 2012."
                    icon={ShieldCheck}
                />

                <div className="flex flex-wrap items-center gap-2">
                    {tabs.map((tab) => (
                        <Button
                            key={tab.key}
                            size="sm"
                            variant={filters.tipo === tab.key ? 'default' : 'outline'}
                            onClick={() => applyFilter(tab.key)}
                        >
                            {tab.label}
                        </Button>
                    ))}
                    <span className="text-muted-foreground ml-auto text-sm">
                        {activities.total} {activities.total === 1 ? 'registro' : 'registros'}
                    </span>
                </div>

                {activities.data.length === 0 ? (
                    <EmptyState
                        icon={ScrollText}
                        title="Sin registros de auditoría"
                        description="Las consultas y cambios sobre datos clínicos aparecerán aquí automáticamente."
                    />
                ) : (
                    <>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Acción</TableHead>
                                    <TableHead>Recurso</TableHead>
                                    <TableHead>Responsable</TableHead>
                                    <TableHead>Origen</TableHead>
                                    <TableHead>Fecha</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activities.data.map((activity) => {
                                    const isAccess = activity.event === 'consultado';

                                    return (
                                        <TableRow key={activity.id}>
                                            <TableCell>
                                                <Badge variant={isAccess ? 'info' : 'accent'}>
                                                    {isAccess ? <Eye /> : <PencilLine />}
                                                    {eventLabels[activity.event ?? ''] ?? activity.event ?? '—'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <span className="font-medium">{subjectLabels[activity.subject] ?? activity.subject}</span>
                                                {activity.subjectId && (
                                                    <span className="text-muted-foreground tabular ml-1 text-xs">#{activity.subjectId}</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="font-medium">{activity.causer}</TableCell>
                                            <TableCell className="text-muted-foreground tabular text-xs">
                                                {(activity.properties?.ip as string) ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">{formatDateTime(activity.createdAt)}</TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>

                        {activities.links.length > 3 && (
                            <div className="flex flex-wrap justify-center gap-1.5">
                                {activities.links.map((link, index) => (
                                    <Button
                                        key={index}
                                        size="sm"
                                        variant={link.active ? 'default' : 'outline'}
                                        disabled={!link.url}
                                        asChild={Boolean(link.url)}
                                    >
                                        {link.url ? (
                                            <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                                        ) : (
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
