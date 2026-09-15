import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { initialsFrom } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search, UserPlus, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Pacientes', href: '/medico/pacientes' },
];

interface PatientRow {
    id: number;
    full_name: string;
    document_type: string;
    document_number: string;
    municipality: string;
    phone: string;
}

export default function PacientesIndex({ patients }: { patients: PatientRow[] }) {
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const term = query.trim().toLowerCase();
        if (!term) return patients;

        return patients.filter(
            (patient) =>
                patient.full_name.toLowerCase().includes(term) ||
                patient.document_number.includes(term) ||
                patient.municipality.toLowerCase().includes(term),
        );
    }, [patients, query]);

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar este paciente? Sus datos dejarán de estar disponibles en los listados.')) {
            router.delete(route('medico.pacientes.destroy', id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pacientes" />

            <div className="space-y-6">
                <PageHeader
                    title="Pacientes"
                    description="Personas inscritas en el programa de enfermedades crónicas no transmisibles."
                    icon={Users}
                    actions={
                        <Button asChild>
                            <Link href={route('medico.pacientes.create')}>
                                <UserPlus />
                                Registrar paciente
                            </Link>
                        </Button>
                    }
                />

                {patients.length > 0 && (
                    <div className="relative max-w-md">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2" />
                        <Input
                            className="pl-10"
                            placeholder="Buscar por nombre, documento o municipio"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                        />
                    </div>
                )}

                {patients.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Aún no hay pacientes"
                        description="Registra el primer paciente para comenzar su historia clínica y su seguimiento."
                        action={
                            <Button asChild>
                                <Link href={route('medico.pacientes.create')}>Registrar paciente</Link>
                            </Button>
                        }
                    />
                ) : filtered.length === 0 ? (
                    <EmptyState icon={Search} title="Sin coincidencias" description={`Ningún paciente coincide con "${query}".`} />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Paciente</TableHead>
                                <TableHead>Documento</TableHead>
                                <TableHead>Municipio</TableHead>
                                <TableHead>Teléfono</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.map((patient) => (
                                <TableRow key={patient.id}>
                                    <TableCell>
                                        <Link href={route('medico.pacientes.show', patient.id)} className="group flex items-center gap-3">
                                            <span className="bg-primary-soft text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                                {initialsFrom(patient.full_name)}
                                            </span>
                                            <span className="font-semibold group-hover:underline">{patient.full_name}</span>
                                        </Link>
                                    </TableCell>
                                    <TableCell className="text-muted-foreground tabular text-sm">
                                        {patient.document_type} {patient.document_number}
                                    </TableCell>
                                    <TableCell className="text-sm">{patient.municipality}</TableCell>
                                    <TableCell className="text-muted-foreground tabular text-sm">{patient.phone}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={route('medico.pacientes.edit', patient.id)}>Editar</Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive hover:bg-destructive-soft"
                                                onClick={() => handleDelete(patient.id)}
                                            >
                                                Eliminar
                                            </Button>
                                        </div>
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
