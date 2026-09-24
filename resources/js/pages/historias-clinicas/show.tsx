import { Field } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FileHeart, Printer, ShieldCheck, Video } from 'lucide-react';
import { type FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Historia clínica', href: '#' }];

interface ClinicalHistoryData {
    id: number;
    medical_history: string | null;
    ecnt_diagnosis: string | null;
    allergies: string | null;
    current_medication: string | null;
    created_at: string;
    patient: {
        id: number;
        full_name: string;
        municipality: string;
        document_type: string;
        document_number: string;
    };
}

interface Clarification {
    id: number;
    body: string;
    authorName: string;
    createdAt: string;
}

interface TeleconsultationNote {
    id: number;
    appointmentId: number;
    notes: string;
    scheduledAt: string;
    doctorName: string | null;
    // Solo el médico de la cita, con la sala cerrada; el paciente lee sin formulario.
    canClarify: boolean;
    clarifications: Clarification[];
}

/**
 * Formulario para aclarar una nota cerrada.
 *
 * La nota no se edita porque la auditoría guarda qué campo cambió, no su
 * valor: reescribirla borraría la versión anterior sin rastro.
 */
function ClarificationForm({ appointmentId }: { appointmentId: number }) {
    // TODO doc: guía de usuario — cómo corregir una nota cerrada con una aclaración.
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });
    const fieldId = `aclaracion-${appointmentId}`;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('medico.citas.teleconsulta.aclaraciones.store', appointmentId), {
            preserveScroll: true,
            onSuccess: () => reset('body'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3 pt-2">
            <Field
                htmlFor={fieldId}
                label="Aclaración"
                error={errors.body}
                hint="Las notas cerradas no se editan. Tu aclaración queda con tu nombre y la fecha, y la nota original se conserva."
            >
                <Textarea id={fieldId} rows={3} maxLength={5000} value={data.body} onChange={(e) => setData('body', e.target.value)} />
            </Field>
            <Button type="submit" size="sm" variant="outline" disabled={processing}>
                Agregar aclaración
            </Button>
        </form>
    );
}

export default function HistoriaClinicaShow({
    clinicalHistory,
    teleconsultationNotes,
}: {
    clinicalHistory: ClinicalHistoryData;
    teleconsultationNotes: TeleconsultationNote[];
}) {
    const sections = [
        { label: 'Antecedentes', value: clinicalHistory.medical_history },
        { label: 'Alergias', value: clinicalHistory.allergies },
        { label: 'Medicación actual', value: clinicalHistory.current_medication },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Historia clínica · ${clinicalHistory.patient.full_name}`} />

            <div className="mx-auto max-w-3xl space-y-6">
                <PageHeader
                    title="Historia clínica"
                    description={`${clinicalHistory.patient.full_name} · ${clinicalHistory.patient.municipality}`}
                    icon={FileHeart}
                    actions={
                        <Button variant="outline" asChild>
                            <a href={route('historias-clinicas.print', clinicalHistory.id)} target="_blank" rel="noreferrer noopener">
                                <Printer />
                                Imprimir
                            </a>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader className="flex-row items-start justify-between space-y-0">
                        <div>
                            <CardTitle>{clinicalHistory.ecnt_diagnosis ?? 'Sin diagnóstico ECNT registrado'}</CardTitle>
                            <p className="text-muted-foreground mt-1 text-sm">Registrada el {formatDate(clinicalHistory.created_at)}</p>
                        </div>
                        <Badge variant="outline">
                            {clinicalHistory.patient.document_type} {clinicalHistory.patient.document_number}
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-border/70 divide-y">
                            {sections.map((section) => (
                                <div key={section.label} className="grid gap-1 py-4 first:pt-0 last:pb-0 sm:grid-cols-4 sm:gap-4">
                                    <dt className="text-muted-foreground text-sm font-medium">{section.label}</dt>
                                    <dd className="text-sm whitespace-pre-line sm:col-span-3">{section.value || '—'}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>

                {teleconsultationNotes.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Video className="size-4.5" aria-hidden="true" />
                                Notas de teleconsultas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-border/70 divide-y">
                                {teleconsultationNotes.map((note) => (
                                    <li key={note.id} className="space-y-1.5 py-4 first:pt-0 last:pb-0">
                                        <p className="text-muted-foreground text-xs">
                                            {formatDateTime(note.scheduledAt)}
                                            {note.doctorName ? ` · ${note.doctorName}` : ''}
                                        </p>
                                        <p className="text-sm whitespace-pre-line">{note.notes}</p>

                                        {note.clarifications.length > 0 && (
                                            <ul className="border-border/70 mt-3 space-y-3 border-l-2 pl-4">
                                                {note.clarifications.map((clarification) => (
                                                    <li key={clarification.id} className="space-y-1">
                                                        <p className="text-muted-foreground text-xs">
                                                            Aclaración · {clarification.authorName} · {formatDateTime(clarification.createdAt)}
                                                        </p>
                                                        <p className="text-sm whitespace-pre-line">{clarification.body}</p>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}

                                        {note.canClarify && <ClarificationForm appointmentId={note.appointmentId} />}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                <div className="text-muted-foreground flex items-start gap-2.5 rounded-xl border border-dashed p-4 text-xs">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    <p>
                        Esta consulta quedó registrada en la auditoría de la plataforma con tu usuario, fecha y hora, conforme a la Ley 1581 de 2012
                        de protección de datos personales.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
