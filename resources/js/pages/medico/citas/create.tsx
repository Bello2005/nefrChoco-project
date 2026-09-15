import { Field, FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarPlus, MonitorSmartphone, Stethoscope } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Citas', href: '/medico/citas' },
    { title: 'Agendar', href: '/medico/citas/create' },
];

const typeOptions = [
    { value: 'presencial', label: 'Presencial', description: 'El paciente asiste a la sede', icon: Stethoscope },
    { value: 'teleconsulta', label: 'Teleconsulta', description: 'Videollamada con sala segura', icon: MonitorSmartphone },
];

export default function CitasCreate({ patients }: { patients: { id: number; full_name: string }[] }) {
    const { data, setData, post, processing, errors } = useForm({
        patient_id: patients[0]?.id.toString() ?? '',
        scheduled_at: '',
        type: 'presencial',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('medico.citas.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Agendar cita" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Agendar cita" description="Programa una atención presencial o una teleconsulta." icon={CalendarPlus} />

                <form onSubmit={submit}>
                    <FormCard>
                        <Field label="Paciente" htmlFor="patient_id" error={errors.patient_id}>
                            <NativeSelect id="patient_id" value={data.patient_id} onChange={(e) => setData('patient_id', e.target.value)} required>
                                {patients.map((patient) => (
                                    <option key={patient.id} value={patient.id}>
                                        {patient.full_name}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>

                        <Field label="Fecha y hora" htmlFor="scheduled_at" error={errors.scheduled_at}>
                            <Input
                                id="scheduled_at"
                                type="datetime-local"
                                value={data.scheduled_at}
                                onChange={(e) => setData('scheduled_at', e.target.value)}
                                required
                            />
                        </Field>

                        <Field label="Modalidad" htmlFor="type" error={errors.type}>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {typeOptions.map((option) => {
                                    const Icon = option.icon;
                                    const isActive = data.type === option.value;

                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() => setData('type', option.value)}
                                            className={cn(
                                                'focus-ring rounded-lg border p-4 text-left transition-all duration-200',
                                                isActive
                                                    ? 'border-ring bg-primary-soft shadow-xs'
                                                    : 'border-border bg-card hover:border-ring/40 hover:bg-muted/60',
                                            )}
                                        >
                                            <Icon className={cn('size-5', isActive ? 'text-accent-foreground' : 'text-muted-foreground')} />
                                            <p className="mt-2 text-sm font-semibold">{option.label}</p>
                                            <p className="text-muted-foreground mt-0.5 text-xs">{option.description}</p>
                                        </button>
                                    );
                                })}
                            </div>
                        </Field>

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing || patients.length === 0}>Agendar cita</Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('medico.citas.index')}>Cancelar</Link>
                            </Button>
                        </div>

                        {patients.length === 0 && (
                            <p className="text-muted-foreground text-sm">Registra al menos un paciente antes de agendar una cita.</p>
                        )}
                    </FormCard>
                </form>
            </div>
        </AppLayout>
    );
}
