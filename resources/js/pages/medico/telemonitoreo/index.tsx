import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatRelative } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Activity, HeartPulse, TriangleAlert } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Telemonitoreo', href: '/medico/telemonitoreo' },
];

interface Reading {
    label: string | null;
    value: number;
    unit: string;
    status: string;
    recordedAt: string;
}

interface PatientRow {
    id: number;
    name: string;
    municipality: string;
    readings: number;
    lastReading: Reading | null;
}

interface Alert {
    id: number;
    patient: string | null;
    patientId: number;
    label: string;
    value: number;
    unit: string;
    status: string;
    recordedAt: string;
}

export default function TelemonitoreoIndex({ patients, alerts }: { patients: PatientRow[]; alerts: Alert[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Telemonitoreo" />

            <div className="space-y-6">
                <PageHeader
                    title="Telemonitoreo de signos vitales"
                    description="Seguimiento remoto de las mediciones reportadas por los pacientes y el equipo en territorio."
                    icon={Activity}
                />

                {alerts.length > 0 && (
                    <Card className="border-warning/30">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TriangleAlert className="text-warning size-4" />
                                Requieren revisión
                            </CardTitle>
                            <p className="text-muted-foreground text-sm">Últimas mediciones fuera del rango de referencia</p>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-2.5 sm:grid-cols-2">
                                {alerts.map((alert) => (
                                    <Link
                                        key={alert.id}
                                        href={route('medico.telemonitoreo.show', alert.patientId)}
                                        className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
                                            alert.status === 'alto'
                                                ? 'border-destructive/25 bg-destructive-soft/50 hover:bg-destructive-soft'
                                                : 'border-warning/25 bg-warning-soft/50 hover:bg-warning-soft'
                                        }`}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">{alert.patient}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {alert.label} · {formatRelative(alert.recordedAt)}
                                            </p>
                                        </div>
                                        <span className="tabular text-sm font-bold">
                                            {alert.value}
                                            <span className="text-muted-foreground ml-0.5 text-xs font-medium">{alert.unit}</span>
                                        </span>
                                        <Badge variant={alert.status === 'alto' ? 'destructive' : 'warning'}>{alert.status}</Badge>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {patients.length === 0 ? (
                    <EmptyState icon={HeartPulse} title="Sin pacientes registrados" description="Registra pacientes para iniciar su seguimiento." />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Paciente</TableHead>
                                <TableHead>Municipio</TableHead>
                                <TableHead>Mediciones</TableHead>
                                <TableHead>Última lectura</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {patients.map((patient) => (
                                <TableRow key={patient.id}>
                                    <TableCell className="font-semibold">{patient.name}</TableCell>
                                    <TableCell className="text-muted-foreground text-sm">{patient.municipality}</TableCell>
                                    <TableCell className="tabular text-sm">{patient.readings}</TableCell>
                                    <TableCell>
                                        {patient.lastReading ? (
                                            <span className="flex items-center gap-2">
                                                <span className="tabular text-sm font-semibold">
                                                    {patient.lastReading.value} {patient.lastReading.unit}
                                                </span>
                                                <Badge
                                                    variant={
                                                        patient.lastReading.status === 'normal'
                                                            ? 'success'
                                                            : patient.lastReading.status === 'alto'
                                                              ? 'destructive'
                                                              : 'warning'
                                                    }
                                                >
                                                    {patient.lastReading.label}
                                                </Badge>
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground text-sm">Sin mediciones</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button size="sm" variant="outline" asChild>
                                            <Link href={route('medico.telemonitoreo.show', patient.id)}>Ver evolución</Link>
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
