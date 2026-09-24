import { CodeSelect } from '@/components/forms/code-select';
import { Field } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
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
    // null en entradas viejas cuyo autor no se pudo recuperar de la auditoría.
    author: { id: number; name: string } | null;
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
    diagnoses: CodedDiagnosis[];
}

interface CodedDiagnosis {
    id: number;
    code: string;
    display: string | null;
    cie11Code: string | null;
    cie11Display: string | null;
    role: string;
    isCurrent: boolean;
    isCorrection: boolean;
    authorName: string | null;
    createdAt: string;
}

/** Diagnósticos de la atención: los reemplazados se ven tachados, nunca desaparecen. */
function DiagnosisList({ diagnoses }: { diagnoses: CodedDiagnosis[] }) {
    return (
        <ul className="space-y-1 text-sm">
            {diagnoses.map((diagnosis) => (
                <li key={diagnosis.id} className={diagnosis.isCurrent ? '' : 'text-muted-foreground line-through'}>
                    <span className="font-semibold">{diagnosis.code}</span>
                    {diagnosis.display ? ` · ${diagnosis.display}` : ''}
                    {diagnosis.cie11Code ? ` (CIE-11 ${diagnosis.cie11Code})` : ''}
                    <span className="text-muted-foreground text-xs">
                        {' '}
                        · {diagnosis.role === 'principal' ? 'principal' : 'relacionado'}
                        {diagnosis.isCorrection && diagnosis.authorName
                            ? ` · corrección de ${diagnosis.authorName}, ${formatDateTime(diagnosis.createdAt)}`
                            : ''}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/**
 * Formulario para aclarar una nota cerrada.
 *
 * La nota no se edita porque la auditoría guarda qué campo cambió, no su
 * valor: reescribirla borraría la versión anterior sin rastro.
 */
function ClarificationForm({ appointmentId, diagnoses, hasCie10 }: { appointmentId: number; diagnoses: CodedDiagnosis[]; hasCie10: boolean }) {
    const { data, setData, post, processing, errors, reset, transform } = useForm({ body: '', replaces_id: '', cie10_code: '' });
    const fieldId = `aclaracion-${appointmentId}`;
    const current = diagnoses.filter((diagnosis) => diagnosis.isCurrent);

    // El diagnóstico corregido solo viaja si se eligió cuál reemplaza y el nuevo código.
    transform((values) => ({
        body: values.body,
        ...(values.replaces_id && values.cie10_code
            ? { corrected_diagnosis: { replaces_id: Number(values.replaces_id), cie10_code: values.cie10_code } }
            : {}),
    }));

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('medico.citas.teleconsulta.aclaraciones.store', appointmentId), {
            preserveScroll: true,
            onSuccess: () => reset(),
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
            {hasCie10 && current.length > 0 && (
                <div className="border-border/70 grid gap-3 rounded-lg border p-3">
                    <p className="text-muted-foreground text-xs">
                        Opcional: si la aclaración corrige un diagnóstico, elige cuál y el código correcto. El original se conserva tachado.
                    </p>
                    <Field
                        htmlFor={`${fieldId}-replaces`}
                        label="Diagnóstico que corriges"
                        error={(errors as Record<string, string>)['corrected_diagnosis.replaces_id']}
                    >
                        <NativeSelect id={`${fieldId}-replaces`} value={data.replaces_id} onChange={(e) => setData('replaces_id', e.target.value)}>
                            <option value="">Ninguno</option>
                            {current.map((diagnosis) => (
                                <option key={diagnosis.id} value={diagnosis.id}>
                                    {diagnosis.code} {diagnosis.display ? `· ${diagnosis.display}` : ''}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    {data.replaces_id && (
                        <Field
                            htmlFor={`${fieldId}-code`}
                            label="Código correcto (CIE-10)"
                            error={(errors as Record<string, string>)['corrected_diagnosis.cie10_code']}
                        >
                            <CodeSelect
                                id={`${fieldId}-code`}
                                system="cie10"
                                value={data.cie10_code}
                                onChange={(code) => setData('cie10_code', code)}
                            />
                        </Field>
                    )}
                </div>
            )}
            <Button type="submit" size="sm" variant="outline" disabled={processing}>
                Agregar aclaración
            </Button>
        </form>
    );
}

export default function HistoriaClinicaShow({
    clinicalHistory,
    historyDiagnoses,
    teleconsultationNotes,
    hasCie10,
}: {
    clinicalHistory: ClinicalHistoryData;
    historyDiagnoses: { code: string; display: string | null }[];
    teleconsultationNotes: TeleconsultationNote[];
    hasCie10: boolean;
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
                            <p className="text-muted-foreground mt-1 text-sm">
                                {clinicalHistory.author
                                    ? `Registrada por ${clinicalHistory.author.name} · ${formatDate(clinicalHistory.created_at)}`
                                    : `Autor no registrado (entrada anterior a este cambio) · ${formatDate(clinicalHistory.created_at)}`}
                            </p>
                        </div>
                        <Badge variant="outline">
                            {clinicalHistory.patient.document_type} {clinicalHistory.patient.document_number}
                        </Badge>
                    </CardHeader>
                    <CardContent>
                        {historyDiagnoses.length > 0 && (
                            <div className="mb-4 space-y-1">
                                <p className="text-muted-foreground text-sm font-medium">Diagnósticos CIE-10</p>
                                <ul className="text-sm">
                                    {historyDiagnoses.map((diagnosis) => (
                                        <li key={diagnosis.code}>
                                            <span className="font-semibold">{diagnosis.code}</span>
                                            {diagnosis.display ? ` · ${diagnosis.display}` : ''}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
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
                                        {note.diagnoses.length > 0 && <DiagnosisList diagnoses={note.diagnoses} />}

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

                                        {note.canClarify && (
                                            <ClarificationForm appointmentId={note.appointmentId} diagnoses={note.diagnoses} hasCie10={hasCie10} />
                                        )}
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
