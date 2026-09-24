import { FormCard } from '@/components/forms/field';
import {
    PatientFields,
    patientFormFrom,
    type BiologicalSexOption,
    type PatientCatalogOptions,
    type PatientCatalogs,
    type PatientCodeLabels,
} from '@/components/forms/patient-fields';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Pacientes', href: '/medico/pacientes' },
    { title: 'Registrar', href: '/medico/pacientes/create' },
];

interface Props {
    biologicalSexOptions: BiologicalSexOption[];
    catalogs: PatientCatalogs;
    catalogOptions: PatientCatalogOptions;
    codeLabels: PatientCodeLabels;
}

export default function PacientesCreate({ biologicalSexOptions, catalogs, catalogOptions, codeLabels }: Props) {
    const { data, setData, post, processing, errors } = useForm(patientFormFrom());

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('medico.pacientes.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar paciente" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="Registrar paciente"
                    description="Datos básicos de identificación y contacto para el seguimiento del programa."
                    icon={UserPlus}
                />

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
                            <Button disabled={processing}>Registrar paciente</Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('medico.pacientes.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </FormCard>
                </form>
            </div>
        </AppLayout>
    );
}
