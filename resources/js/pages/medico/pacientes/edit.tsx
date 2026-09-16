import { FormCard } from '@/components/forms/field';
import { PatientFields, type BiologicalSexOption } from '@/components/forms/patient-fields';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Pacientes', href: '/medico/pacientes' },
    { title: 'Editar', href: '#' },
];

interface PatientData {
    id: number;
    full_name: string;
    document_type: string;
    document_number: string;
    birth_date: string;
    biological_sex: string | null;
    municipality: string;
    phone: string;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
}

export default function PacientesEdit({
    patient,
    biologicalSexOptions,
}: {
    patient: PatientData;
    biologicalSexOptions: BiologicalSexOption[];
}) {
    const { data, setData, put, processing, errors } = useForm({
        full_name: patient.full_name,
        document_type: patient.document_type,
        document_number: patient.document_number,
        birth_date: patient.birth_date.slice(0, 10),
        // Vacío en las fichas anteriores a que el dato existiera.
        biological_sex: patient.biological_sex ?? '',
        municipality: patient.municipality,
        phone: patient.phone,
        emergency_contact_name: patient.emergency_contact_name ?? '',
        emergency_contact_phone: patient.emergency_contact_phone ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('medico.pacientes.update', patient.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${patient.full_name}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Editar paciente" description={patient.full_name} icon={UserRoundCog} />

                <form onSubmit={submit}>
                    <FormCard>
                        <PatientFields data={data} errors={errors} setData={setData} biologicalSexOptions={biologicalSexOptions} />

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing}>Guardar cambios</Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('medico.pacientes.show', patient.id)}>Cancelar</Link>
                            </Button>
                        </div>
                    </FormCard>
                </form>
            </div>
        </AppLayout>
    );
}
