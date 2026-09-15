import { BarChart, DonutChart } from '@/components/charts';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatRelative } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, CalendarRange, ClipboardList, LayoutDashboard, MapPin, ScrollText, UserPlus, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Panel administrativo', href: '/admin' }];

const roleLabels: Record<string, string> = {
    admin: 'Administradores',
    medico: 'Médicos',
    paciente: 'Pacientes',
    sin_rol: 'Sin rol asignado',
};

const rolePalette: Record<string, string> = {
    admin: 'var(--chart-3)',
    medico: 'var(--chart-1)',
    paciente: 'var(--chart-2)',
    sin_rol: 'var(--chart-4)',
};

const eventLabels: Record<string, string> = {
    created: 'creó',
    updated: 'actualizó',
    deleted: 'eliminó',
    consultado: 'consultó',
};

const subjectLabels: Record<string, string> = {
    Patient: 'un paciente',
    ClinicalHistory: 'una historia clínica',
    VitalSign: 'un signo vital',
    ClinicalForm: 'un formulario clínico',
};

interface Props {
    stats: { users: number; patients: number; appointmentsMonth: number; clinicalForms: number };
    usersByRole: Record<string, number>;
    patientsByMunicipality: { label: string; value: number }[];
    recentActivity: {
        id: number;
        description: string;
        event: string | null;
        subject: string;
        causer: string;
        createdAt: string;
    }[];
}

export default function AdminDashboard({ stats, usersByRole, patientsByMunicipality, recentActivity }: Props) {
    const roleEntries = Object.entries(usersByRole);
    const totalUsers = roleEntries.reduce((sum, [, count]) => sum + count, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Panel administrativo" />

            <div className="space-y-6">
                <PageHeader
                    title="Panel administrativo"
                    description="Estado general de la plataforma, cobertura territorial y trazabilidad de datos clínicos."
                    icon={LayoutDashboard}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={route('admin.educativo.index')}>
                                    <BookOpen />
                                    Contenido
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={route('admin.usuarios.create')}>
                                    <UserPlus />
                                    Nuevo usuario
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Usuarios" value={stats.users} icon={Users} tone="primary" />
                    <StatCard label="Pacientes" value={stats.patients} icon={Users} tone="success" />
                    <StatCard label="Citas este mes" value={stats.appointmentsMonth} icon={CalendarRange} tone="info" />
                    <StatCard label="Formularios aplicados" value={stats.clinicalForms} icon={ClipboardList} tone="brand" />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Usuarios por rol</CardTitle>
                            <p className="text-muted-foreground text-sm">Composición de las cuentas activas</p>
                        </CardHeader>
                        <CardContent>
                            {totalUsers === 0 ? (
                                <EmptyState icon={Users} title="Aún no hay usuarios" />
                            ) : (
                                <DonutChart
                                    total={totalUsers}
                                    caption="cuentas"
                                    segments={roleEntries.map(([role, count]) => ({
                                        label: roleLabels[role] ?? role,
                                        value: count,
                                        color: rolePalette[role] ?? 'var(--chart-5)',
                                    }))}
                                />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Cobertura por municipio</CardTitle>
                            <p className="text-muted-foreground text-sm">Pacientes registrados en el territorio</p>
                        </CardHeader>
                        <CardContent>
                            {patientsByMunicipality.length === 0 ? (
                                <EmptyState icon={MapPin} title="Sin pacientes registrados" />
                            ) : (
                                <BarChart data={patientsByMunicipality} color="var(--chart-2)" />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <div>
                            <CardTitle>Actividad reciente</CardTitle>
                            <p className="text-muted-foreground mt-1 text-sm">Trazabilidad de accesos y cambios sobre datos clínicos</p>
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={route('admin.auditoria.index')}>
                                <ScrollText />
                                Auditoría completa
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {recentActivity.length === 0 ? (
                            <EmptyState icon={ScrollText} title="Sin actividad registrada todavía" />
                        ) : (
                            <ul className="divide-border/70 divide-y">
                                {recentActivity.map((activity) => (
                                    <li key={activity.id} className="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                                        <span className="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-lg">
                                            <ScrollText className="size-4" />
                                        </span>
                                        <p className="min-w-0 flex-1 text-sm">
                                            <span className="font-semibold">{activity.causer}</span>{' '}
                                            <span className="text-muted-foreground">
                                                {eventLabels[activity.event ?? ''] ?? activity.event ?? 'modificó'}{' '}
                                                {subjectLabels[activity.subject] ?? activity.subject}
                                            </span>
                                        </p>
                                        <span className="text-muted-foreground shrink-0 text-xs">{formatRelative(activity.createdAt)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
