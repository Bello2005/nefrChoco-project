import { DecisionSupportNotice, RecommendationList } from '@/components/clinical-recommendations';
import { EgfrSeries, kdigoVariants, type EgfrPoint } from '@/components/egfr-series';
import { Field } from '@/components/forms/field';
import { AppointmentStatusBadge, AppointmentTypeBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { calculateAge, formatDateTime, formatRelative, formatShortDate, initialsFrom } from '@/lib/format';
import { type BreadcrumbItem, type ClinicalRecommendation } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Activity, CalendarPlus, ClipboardList, FileHeart, HeartPulse, ListChecks, Pencil, Phone, TriangleAlert } from 'lucide-react';
import { type FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Pacientes', href: '/medico/pacientes' },
    { title: 'Ficha', href: '#' },
];

const riskVariants: Record<string, 'success' | 'info' | 'warning' | 'destructive'> = {
    bajo: 'success',
    adherente: 'success',
    ligero: 'info',
    moderado: 'warning',
    parcial: 'warning',
    alto: 'destructive',
    no_adherente: 'destructive',
};

interface ClinicalHistoryRow {
    id: number;
    ecnt_diagnosis: string | null;
    medical_history: string | null;
    allergies: string | null;
    current_medication: string | null;
    created_at: string;
    author: { id: number; name: string } | null;
}

interface AppointmentRow {
    id: number;
    scheduled_at: string;
    status: string;
    type: string;
    doctor: { id: number; name: string } | null;
}

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
    clinical_histories: ClinicalHistoryRow[];
    appointments: AppointmentRow[];
}

interface Props {
    patient: PatientData;
    recommendations: ClinicalRecommendation[];
    egfrSeries: EgfrPoint[];
    clinicalForms: {
        id: number;
        templateName: string;
        riskLevel: string | null;
        score: number | null;
        egfr: number | null;
        kdigoG: string | null;
        kdigoA: string | null;
        createdAt: string;
    }[];
    vitalSigns: { id: number; label: string; value: number; unit: string; status: string; recordedAt: string }[];
    followUp: { level: string | null; options: { value: string; label: string }[]; isActive: boolean; overdue: string[] };
    attentionDiagnoses: Record<number, { code: string; display: string | null; role: string }[]>;
}

/**
 * Nivel de riesgo para el seguimiento remoto (Res. 1644 de 2026, art. 19 par. 1).
 * Lo asigna el médico; decide cada cuánto se espera una medición del paciente.
 */
function FollowUpCard({ patientId, followUp }: { patientId: number; followUp: Props['followUp'] }) {
    const { data, setData, patch, processing } = useForm({ follow_up_risk_level: followUp.level ?? '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('medico.pacientes.riesgo-seguimiento', patientId), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm">Seguimiento remoto</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <form onSubmit={submit} className="space-y-3">
                    <Field htmlFor="follow_up_risk_level" label="Nivel de riesgo para el seguimiento">
                        <NativeSelect
                            id="follow_up_risk_level"
                            value={data.follow_up_risk_level}
                            onChange={(e) => setData('follow_up_risk_level', e.target.value)}
                        >
                            <option value="">Sin asignar</option>
                            {followUp.options.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </NativeSelect>
                    </Field>
                    <Button type="submit" size="sm" variant="outline" disabled={processing}>
                        Guardar nivel
                    </Button>
                </form>
                {!followUp.isActive ? (
                    <p className="text-muted-foreground text-xs">
                        La frecuencia de seguimiento por nivel todavía no está definida: por ahora el nivel se guarda, pero no genera controles
                        vencidos ni recordatorios.
                    </p>
                ) : followUp.overdue.length > 0 ? (
                    <p className="text-warning text-xs font-semibold">Control vencido: {followUp.overdue.join(', ')}</p>
                ) : null}
            </CardContent>
        </Card>
    );
}

export default function PacientesShow({ patient, recommendations, egfrSeries, clinicalForms, vitalSigns, followUp, attentionDiagnoses }: Props) {
    const latestHistory = patient.clinical_histories[0];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={patient.full_name} />

            <div className="space-y-6">
                <div className="bg-card border-border/70 flex flex-wrap items-center gap-5 rounded-xl border p-6 shadow-sm">
                    <span className="bg-primary text-primary-foreground flex size-16 shrink-0 items-center justify-center rounded-2xl text-xl font-extrabold">
                        {initialsFrom(patient.full_name)}
                    </span>

                    <div className="min-w-0 flex-1">
                        <h1 className="text-2xl font-extrabold">{patient.full_name}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {calculateAge(patient.birth_date)} años · {patient.municipality} · {patient.document_type} {patient.document_number}
                        </p>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            {latestHistory?.ecnt_diagnosis && <Badge variant="accent">{latestHistory.ecnt_diagnosis}</Badge>}
                            <Badge variant="outline">
                                <Phone />
                                {patient.phone}
                            </Badge>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('medico.pacientes.edit', patient.id)}>
                                <Pencil />
                                Editar
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={route('medico.citas.create')}>
                                <CalendarPlus />
                                Agendar cita
                            </Link>
                        </Button>
                    </div>
                </div>

                {(patient.biological_sex === 'indeterminado' || patient.biological_sex === 'desconocido') && (
                    <div className="border-info/30 bg-info-soft flex items-start gap-3 rounded-xl border p-4">
                        <TriangleAlert className="text-info mt-0.5 size-5 shrink-0" aria-hidden="true" />
                        <p className="text-sm">
                            <span className="font-semibold">La TFGe no se calcula para esta ficha.</span> La fórmula de función renal (CKD-EPI) solo
                            contempla sexo biológico femenino o masculino, y en esta ficha está registrado como{' '}
                            {patient.biological_sex === 'indeterminado' ? 'indeterminado' : 'desconocido'}.
                        </p>
                    </div>
                )}

                {!patient.biological_sex && (
                    <div className="border-warning/30 bg-warning-soft flex items-start gap-3 rounded-xl border p-4">
                        <TriangleAlert className="text-warning mt-0.5 size-5 shrink-0" aria-hidden="true" />
                        <p className="text-sm">
                            <span className="font-semibold">Falta el sexo biológico en esta ficha.</span> Es un dato que se pide solo para
                            calcular la función renal (TFGe), y sin él no se puede aplicar el seguimiento de enfermedad renal crónica.{' '}
                            <Link href={route('medico.pacientes.edit', patient.id)} className="text-primary font-semibold hover:underline">
                                Completar la ficha
                            </Link>
                            .
                        </p>
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-3">
                    <Button variant="outline" className="h-auto justify-start py-3" asChild>
                        <Link href={route('medico.telemonitoreo.show', patient.id)}>
                            <Activity />
                            Ver telemonitoreo
                        </Link>
                    </Button>
                    <Button variant="outline" className="h-auto justify-start py-3" asChild>
                        <Link href={route('medico.formularios-clinicos.create', { paciente: patient.id })}>
                            <ClipboardList />
                            Aplicar formulario
                        </Link>
                    </Button>
                    <Button variant="outline" className="h-auto justify-start py-3" asChild>
                        <Link href={route('medico.pacientes.historia-clinica.create', patient.id)}>
                            <FileHeart />
                            Nueva entrada clínica
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-5 lg:grid-cols-3">
                    <div className="space-y-5 lg:col-span-2">
                        {recommendations.length > 0 && (
                            <Card className="border-warning/30">
                                <CardHeader>
                                    <div className="flex items-center gap-2.5">
                                        <span className="bg-warning-soft text-warning flex size-9 items-center justify-center rounded-lg">
                                            <ListChecks className="size-4.5" aria-hidden="true" />
                                        </span>
                                        <CardTitle>Apoyo a la decisión</CardTitle>
                                    </div>
                                    <DecisionSupportNotice className="mt-1.5" />
                                </CardHeader>
                                <CardContent>
                                    <RecommendationList recommendations={recommendations} />
                                </CardContent>
                            </Card>
                        )}

                        <EgfrSeries points={egfrSeries} />

                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <CardTitle>Historia clínica</CardTitle>
                                <Button size="sm" variant="ghost" asChild>
                                    <Link href={route('medico.pacientes.historia-clinica.create', patient.id)}>Nueva entrada</Link>
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {patient.clinical_histories.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">Este paciente aún no tiene historia clínica registrada.</p>
                                ) : (
                                    <ul className="space-y-3">
                                        {patient.clinical_histories.map((history) => (
                                            <li key={history.id} className="border-border/70 rounded-lg border p-4">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <p className="font-semibold">{history.ecnt_diagnosis ?? 'Sin diagnóstico ECNT'}</p>
                                                    <span className="text-muted-foreground text-xs">{formatShortDate(history.created_at)}</span>
                                                </div>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {history.author
                                                        ? `Registrada por ${history.author.name}`
                                                        : 'Autor no registrado (entrada anterior a este cambio)'}
                                                </p>
                                                <dl className="text-muted-foreground mt-2.5 grid gap-1.5 text-sm">
                                                    <div className="flex gap-2">
                                                        <dt className="shrink-0">Alergias:</dt>
                                                        <dd className="text-foreground">{history.allergies ?? '—'}</dd>
                                                    </div>
                                                    <div className="flex gap-2">
                                                        <dt className="shrink-0">Medicación:</dt>
                                                        <dd className="text-foreground">{history.current_medication ?? '—'}</dd>
                                                    </div>
                                                </dl>
                                                <Link
                                                    href={route('historias-clinicas.show', history.id)}
                                                    className="text-primary mt-3 inline-block text-xs font-semibold hover:underline"
                                                >
                                                    Ver detalle completo
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Citas</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {patient.appointments.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">Sin citas agendadas.</p>
                                ) : (
                                    <ul className="divide-border/70 divide-y">
                                        {patient.appointments.map((appointment) => (
                                            <li key={appointment.id} className="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0">
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-semibold">{formatDateTime(appointment.scheduled_at)}</p>
                                                    <p className="text-muted-foreground text-xs">{appointment.doctor?.name}</p>
                                                    {attentionDiagnoses[appointment.id]?.map((diagnosis) => (
                                                        <p key={diagnosis.code} className="text-xs">
                                                            <span className="font-semibold">{diagnosis.code}</span>
                                                            {diagnosis.display ? ` · ${diagnosis.display}` : ''}
                                                            {diagnosis.role === 'principal' ? ' (principal)' : ''}
                                                        </p>
                                                    ))}
                                                </div>
                                                <AppointmentTypeBadge type={appointment.type} />
                                                <AppointmentStatusBadge status={appointment.status} />
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-5">
                        <FollowUpCard patientId={patient.id} followUp={followUp} />

                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <CardTitle className="text-sm">Últimas mediciones</CardTitle>
                                <Button size="sm" variant="ghost" asChild>
                                    <Link href={route('medico.telemonitoreo.show', patient.id)}>
                                        <HeartPulse />
                                    </Link>
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {vitalSigns.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">Sin mediciones registradas.</p>
                                ) : (
                                    <ul className="space-y-2.5">
                                        {vitalSigns.map((sign) => (
                                            <li key={sign.id} className="flex items-center justify-between gap-2 text-sm">
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium">{sign.label}</p>
                                                    <p className="text-muted-foreground text-xs">{formatRelative(sign.recordedAt)}</p>
                                                </div>
                                                <span
                                                    className={`tabular shrink-0 font-bold ${
                                                        sign.status === 'alto'
                                                            ? 'text-destructive'
                                                            : sign.status === 'bajo'
                                                              ? 'text-warning'
                                                              : 'text-foreground'
                                                    }`}
                                                >
                                                    {sign.value}
                                                    <span className="text-muted-foreground ml-0.5 text-xs font-medium">{sign.unit}</span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Formularios aplicados</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {clinicalForms.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">Ningún instrumento aplicado todavía.</p>
                                ) : (
                                    <ul className="space-y-2.5">
                                        {clinicalForms.map((form) => (
                                            <li key={form.id}>
                                                <Link
                                                    href={route('medico.formularios-clinicos.show', form.id)}
                                                    className="hover:bg-muted/50 -mx-2 flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 transition-colors"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">{form.templateName}</p>
                                                        <p className="text-muted-foreground text-xs">{formatShortDate(form.createdAt)}</p>
                                                    </div>
                                                    {form.riskLevel ? (
                                                        <Badge variant={riskVariants[form.riskLevel] ?? 'secondary'} className="shrink-0">
                                                            {form.score}
                                                        </Badge>
                                                    ) : (
                                                        // El seguimiento renal no puntúa: su resultado es la
                                                        // categoría KDIGO, no un número dentro de una escala.
                                                        form.kdigoG && (
                                                            <Badge variant={kdigoVariants[form.kdigoG] ?? 'secondary'} className="shrink-0">
                                                                {form.kdigoG}
                                                                {form.kdigoA ? ` · ${form.kdigoA}` : ''}
                                                            </Badge>
                                                        )
                                                    )}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Contacto de emergencia</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1 text-sm">
                                <p className="font-medium">{patient.emergency_contact_name ?? '—'}</p>
                                <p className="text-muted-foreground tabular">{patient.emergency_contact_phone ?? '—'}</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
