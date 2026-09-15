import { Field, FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FileHeart } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Pacientes', href: '/medico/pacientes' },
    { title: 'Historia clínica', href: '#' },
];

const commonDiagnoses = [
    'Hipertensión arterial',
    'Diabetes mellitus tipo 2',
    'Enfermedad renal crónica',
    'Obesidad',
    'Riesgo cardiovascular',
    'Dislipidemia',
];

export default function HistoriaClinicaCreate({ patient }: { patient: { id: number; full_name: string } }) {
    const { data, setData, post, processing, errors } = useForm({
        ecnt_diagnosis: '',
        medical_history: '',
        allergies: '',
        current_medication: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('medico.pacientes.historia-clinica.store', patient.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Historia clínica · ${patient.full_name}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Nueva entrada de historia clínica" description={`Paciente: ${patient.full_name}`} icon={FileHeart} />

                <form onSubmit={submit}>
                    <FormCard>
                        <Field label="Diagnóstico ECNT" htmlFor="ecnt_diagnosis" error={errors.ecnt_diagnosis}>
                            <Input
                                id="ecnt_diagnosis"
                                list="diagnosticos-ecnt"
                                value={data.ecnt_diagnosis}
                                onChange={(e) => setData('ecnt_diagnosis', e.target.value)}
                                placeholder="Ej. Hipertensión arterial"
                            />
                            <datalist id="diagnosticos-ecnt">
                                {commonDiagnoses.map((diagnosis) => (
                                    <option key={diagnosis} value={diagnosis} />
                                ))}
                            </datalist>
                        </Field>

                        <Field label="Antecedentes" htmlFor="medical_history" error={errors.medical_history}>
                            <Textarea
                                id="medical_history"
                                className="min-h-28"
                                value={data.medical_history}
                                onChange={(e) => setData('medical_history', e.target.value)}
                            />
                        </Field>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Alergias" htmlFor="allergies" error={errors.allergies}>
                                <Textarea id="allergies" value={data.allergies} onChange={(e) => setData('allergies', e.target.value)} />
                            </Field>

                            <Field label="Medicación actual" htmlFor="current_medication" error={errors.current_medication}>
                                <Textarea
                                    id="current_medication"
                                    value={data.current_medication}
                                    onChange={(e) => setData('current_medication', e.target.value)}
                                />
                            </Field>
                        </div>

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing}>Guardar historia clínica</Button>
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
