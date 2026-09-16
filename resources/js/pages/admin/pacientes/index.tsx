import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { initialsFrom } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ShieldAlert, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel', href: '/admin' },
    { title: 'Pacientes', href: '/admin/pacientes' },
];

interface PatientRow {
    id: number;
    fullName: string;
    document: string;
    municipality: string;
    clinicalHistories: number;
    appointments: number;
    vitalSigns: number;
}

export default function AdminPacientesIndex({ patients }: { patients: PatientRow[] }) {
    const handleDelete = (patient: PatientRow) => {
        const total = patient.clinicalHistories + patient.appointments + patient.vitalSigns;

        const message =
            `Vas a eliminar la ficha de ${patient.fullName}.\n\n` +
            `Se dejarán de mostrar ${patient.clinicalHistories} entrada(s) de historia clínica, ` +
            `${patient.appointments} cita(s) y ${patient.vitalSigns} medición(es).\n\n` +
            (total > 0 ? 'Esta acción no se deshace desde la plataforma. ¿Continuar?' : '¿Continuar?');

        if (confirm(message)) {
            router.delete(route('admin.pacientes.destroy', patient.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pacientes" />

            <div className="space-y-6">
                <PageHeader
                    title="Custodia del padrón"
                    description="Fichas inscritas en el programa. El registro y la edición clínica se hacen desde la zona médica; aquí solo se elimina."
                    icon={Users}
                />

                <div className="border-warning/30 bg-warning-soft flex items-start gap-3 rounded-xl border p-4">
                    <ShieldAlert className="text-warning mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <p className="text-sm">
                        Eliminar una ficha arrastra su historia clínica, sus controles y sus mediciones. Hazlo solo cuando corresponda por solicitud
                        del titular o depuración de registros de prueba.
                    </p>
                </div>

                {patients.length === 0 ? (
                    <EmptyState icon={Users} title="No hay pacientes registrados" description="Las fichas se crean desde la zona médica." />
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Paciente</TableHead>
                                    <TableHead>Documento</TableHead>
                                    <TableHead>Municipio</TableHead>
                                    <TableHead>Datos clínicos</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {patients.map((patient) => (
                                    <TableRow key={patient.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <span
                                                    className="bg-primary-soft text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                                    aria-hidden="true"
                                                >
                                                    {initialsFrom(patient.fullName)}
                                                </span>
                                                <span className="font-semibold">{patient.fullName}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular text-sm">{patient.document}</TableCell>
                                        <TableCell className="text-sm">{patient.municipality}</TableCell>
                                        <TableCell className="text-muted-foreground tabular text-sm">
                                            {patient.clinicalHistories} historia(s) · {patient.appointments} cita(s) · {patient.vitalSigns}{' '}
                                            medición(es)
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:bg-destructive-soft"
                                                    onClick={() => handleDelete(patient)}
                                                    aria-label={`Eliminar la ficha de ${patient.fullName}`}
                                                >
                                                    Eliminar
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
