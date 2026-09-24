import { FormCard } from '@/components/forms/field';
import {
    PatientFields,
    patientFormFrom,
    type BiologicalSexOption,
    type PatientCatalogOptions,
    type PatientCatalogs,
    type PatientCodeLabels,
    type PatientFormData,
} from '@/components/forms/patient-fields';
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

type PatientData = Partial<Record<keyof PatientFormData, string | null>> & { id: number; full_name: string };

export default function PacientesEdit({
    patient,
    biologicalSexOptions,
    catalogs,
    catalogOptions,
    codeLabels,
}: {
    patient: PatientData;
    biologicalSexOptions: BiologicalSexOption[];
    catalogs: PatientCatalogs;
    catalogOptions: PatientCatalogOptions;
    codeLabels: PatientCodeLabels;
}) {
    const { data, setData, put, processing, errors } = useForm(patientFormFrom(patient));

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
                        <PatientFields
                            data={data}
                            errors={errors}
                            setData={setData}
                            biologicalSexOptions={biologicalSexOptions}
                            catalogs={catalogs}
                            catalogOptions={catalogOptions}
                            codeLabels={codeLabels}
                        />

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
