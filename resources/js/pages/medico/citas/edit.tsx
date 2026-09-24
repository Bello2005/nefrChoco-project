import { type AttentionCatalogs, AttentionRecordFields, cleanAttention, emptyAttention } from '@/components/forms/attention-record-fields';
import { Field, FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarCog } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Citas', href: '/medico/citas' },
    { title: 'Editar', href: '#' },
];

interface AppointmentData {
    id: number;
    patient_id: number;
    scheduled_at: string;
    status: string;
    type: string;
}

function toDatetimeLocal(value: string): string {
    const date = new Date(value);
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function CitasEdit({
    appointment,
    patients,
    attentionCatalogs,
}: {
    appointment: AppointmentData;
    patients: { id: number; full_name: string }[];
    attentionCatalogs: AttentionCatalogs;
}) {
    const { data, setData, put, processing, errors, transform } = useForm({
        patient_id: appointment.patient_id.toString(),
        scheduled_at: toDatetimeLocal(appointment.scheduled_at),
        type: appointment.type,
        status: appointment.status,
        ...emptyAttention(),
    });

    // Marcarla completada es cerrar la atención: solo entonces viaja el registro.
    const closes = data.status === 'completada';
    transform((values) =>
        values.status === 'completada'
            ? { ...values, ...cleanAttention(values) }
            : { patient_id: values.patient_id, scheduled_at: values.scheduled_at, type: values.type, status: values.status },
    );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('medico.citas.update', appointment.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar cita" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Editar cita" description="Actualiza la programación o el estado de la atención." icon={CalendarCog} />

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

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Modalidad" htmlFor="type" error={errors.type}>
                                <NativeSelect id="type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                    <option value="presencial">Presencial</option>
                                    <option value="teleconsulta">Teleconsulta</option>
                                </NativeSelect>
                            </Field>

                            <Field label="Estado" htmlFor="status" error={errors.status}>
                                <NativeSelect id="status" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                    <option value="programada">Programada</option>
                                    <option value="completada">Completada</option>
                                    <option value="cancelada">Cancelada</option>
                                    <option value="no_asistio">No asistió</option>
                                </NativeSelect>
                            </Field>
                        </div>

                        {closes && (
                            <div className="border-border/70 border-t pt-5">
                                <AttentionRecordFields
                                    data={data}
                                    setData={setData}
                                    errors={errors as Record<string, string | undefined>}
                                    catalogs={attentionCatalogs}
                                />
                            </div>
                        )}

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing}>{closes ? 'Guardar y cerrar la atención' : 'Guardar cambios'}</Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('medico.citas.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </FormCard>
                </form>
            </div>
        </AppLayout>
    );
}
