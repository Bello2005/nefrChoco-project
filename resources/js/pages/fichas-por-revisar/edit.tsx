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
import { reasonLabels } from '@/pages/fichas-por-revisar/index';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { UserRoundSearch } from 'lucide-react';
import { type FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Fichas por revisar', href: '/fichas-por-revisar' },
    { title: 'Completar', href: '#' },
];

type PatientData = Partial<Record<keyof PatientFormData, string | null>> & {
    id: number;
    full_name: string;
    identity_review_reasons: string[] | null;
};

interface Props {
    patient: PatientData;
    biologicalSexOptions: BiologicalSexOption[];
    catalogs: PatientCatalogs;
    catalogOptions: PatientCatalogOptions;
    codeLabels: PatientCodeLabels;
}

export default function CompletarFicha({ patient, biologicalSexOptions, catalogs, catalogOptions, codeLabels }: Props) {
    const { data, setData, put, processing, errors } = useForm(patientFormFrom(patient));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('fichas-por-revisar.update', patient.id));
    };

    const reasons = patient.identity_review_reasons ?? [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Completar ${patient.full_name}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Completar ficha" description={patient.full_name} icon={UserRoundSearch} />

                {reasons.length > 0 && (
                    <div className="border-warning/30 bg-warning-soft rounded-xl border p-4 text-sm">
                        <p className="font-semibold">Revisa con el documento del paciente:</p>
                        <ul className="mt-1 list-disc pl-5">
                            {reasons.map((reason) => (
                                <li key={reason}>{reasonLabels[reason] ?? reason}</li>
                            ))}
                        </ul>
                        {reasons.includes('nombres') && (
                            <p className="text-muted-foreground mt-2 text-xs">
                                Los nombres y apellidos se separaron automáticamente a partir del nombre completo y pueden estar mal. Corrígelos si
                                hace falta.
                            </p>
                        )}
                    </div>
                )}

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
                            <Button disabled={processing}>Guardar y marcar como revisada</Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('fichas-por-revisar.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </FormCard>
                </form>
            </div>
        </AppLayout>
    );
}
