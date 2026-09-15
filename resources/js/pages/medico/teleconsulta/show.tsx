import { Field } from '@/components/forms/field';
import { JitsiMeeting } from '@/components/jitsi-meeting';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, initialsFrom } from '@/lib/format';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ShieldCheck, UserRound } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Citas', href: '/medico/citas' },
    { title: 'Teleconsulta', href: '#' },
];

interface Props {
    appointment: {
        id: number;
        scheduled_at: string;
        patient: { id: number; full_name: string };
    };
    teleconsultation: { id: number; room_name: string; status: string; notes: string | null };
    jitsiDomain: string;
}

export default function TeleconsultaShow({ appointment, teleconsultation, jitsiDomain }: Props) {
    const { auth } = usePage<SharedData>().props;
    const isFinished = teleconsultation.status === 'finalizada';

    const { data, setData, post, processing, errors } = useForm({
        notes: teleconsultation.notes ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('medico.citas.teleconsulta.complete', appointment.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Teleconsulta · ${appointment.patient.full_name}`} />

            <div className="space-y-5">
                <div className="flex flex-wrap items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={route('medico.citas.index')} aria-label="Volver a la agenda">
                            <ArrowLeft />
                        </Link>
                    </Button>

                    <span className="bg-primary text-primary-foreground flex size-11 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold">
                        {initialsFrom(appointment.patient.full_name)}
                    </span>

                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-xl font-extrabold">{appointment.patient.full_name}</h1>
                        <p className="text-muted-foreground text-sm">{formatDateTime(appointment.scheduled_at)}</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {isFinished ? (
                            <Badge variant="success">
                                <CheckCircle2 />
                                Consulta cerrada
                            </Badge>
                        ) : (
                            <Badge variant="accent">
                                <ShieldCheck />
                                Sala privada
                            </Badge>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={route('medico.pacientes.show', appointment.patient.id)}>
                                <UserRound />
                                Ver ficha
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <JitsiMeeting domain={jitsiDomain} roomName={teleconsultation.room_name} displayName={auth.user.name} />
                        <p className="text-muted-foreground mt-3 text-center text-xs">
                            El nombre de la sala es único y no adivinable. Si la conexión es inestable, desactiva tu cámara para priorizar el audio.
                        </p>
                    </div>

                    <form onSubmit={submit} className="bg-card border-border/70 h-fit space-y-4 rounded-xl border p-5 shadow-sm">
                        <div>
                            <h2 className="font-display text-base font-bold">Notas de la consulta</h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Quedan en la historia del paciente y cierran la cita como completada.
                            </p>
                        </div>

                        <Field htmlFor="notes" error={errors.notes}>
                            <Textarea
                                id="notes"
                                className="min-h-56"
                                placeholder="Motivo de consulta, hallazgos, conducta y próximo control…"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                        </Field>

                        <Button className="w-full" disabled={processing}>
                            {isFinished ? 'Actualizar notas' : 'Cerrar teleconsulta'}
                        </Button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
