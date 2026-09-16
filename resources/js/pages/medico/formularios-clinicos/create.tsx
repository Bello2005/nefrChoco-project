import { Field, FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardList, Sparkles } from 'lucide-react';
import { FormEventHandler, useMemo } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Formularios clínicos', href: '/medico/formularios-clinicos' },
    { title: 'Aplicar', href: '/medico/formularios-clinicos/crear' },
];

interface TemplateOption {
    value: string;
    label: string;
    score?: number;
}

interface TemplateField {
    key: string;
    label: string;
    type: 'select' | 'number' | 'textarea' | 'date';
    options?: TemplateOption[];
    optional?: boolean;
    min?: number;
    max?: number;
}

interface Threshold {
    max: number;
    level: string;
    label: string;
    tone: string;
    advice: string;
}

interface Template {
    key: string;
    name: string;
    description: string;
    fields: TemplateField[];
    scoring: { enabled: boolean; thresholds?: Threshold[] };
}

interface Props {
    templates: Template[];
    selectedTemplate: Template | null;
    patients: { id: number; full_name: string }[];
    preselectedPatient: number | null;
}

const toneStyles: Record<string, string> = {
    success: 'border-success/30 bg-success-soft text-success',
    info: 'border-info/30 bg-info-soft text-info',
    warning: 'border-warning/30 bg-warning-soft text-warning',
    destructive: 'border-destructive/30 bg-destructive-soft text-destructive',
};

export default function FormulariosCreate({ templates, selectedTemplate, patients, preselectedPatient }: Props) {
    const template = selectedTemplate ?? templates[0];

    const { data, setData, post, processing, errors } = useForm<{
        patient_id: string;
        form_type: string;
        answers: Record<string, string>;
    }>({
        patient_id: (preselectedPatient ?? patients[0]?.id ?? '').toString(),
        form_type: template?.key ?? '',
        answers: {},
    });

    // Puntaje en vivo: el profesional ve la interpretación mientras responde,
    // sin esperar a guardar. El backend la recalcula al persistir.
    const livePreview = useMemo(() => {
        if (!template?.scoring.enabled) return null;

        let total = 0;
        let answered = 0;
        let scorable = 0;

        template.fields.forEach((field) => {
            if (field.type !== 'select' || field.options?.[0]?.score === undefined) return;

            scorable++;
            const answer = data.answers[field.key];
            const option = field.options.find((item) => item.value === answer);

            if (option) {
                answered++;
                total += option.score ?? 0;
            }
        });

        const threshold = template.scoring.thresholds?.find((item) => total <= item.max);

        return { total, threshold, complete: answered === scorable && scorable > 0 };
    }, [template, data.answers]);

    const changeTemplate = (key: string) => {
        router.get(route('medico.formularios-clinicos.create'), { tipo: key, paciente: data.patient_id }, { preserveState: false });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('medico.formularios-clinicos.store'));
    };

    if (!template) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Aplicar formulario" />
                <p className="text-muted-foreground">No hay instrumentos disponibles.</p>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={template.name} />

            <div className="space-y-6">
                <PageHeader title={template.name} description={template.description} icon={ClipboardList} />

                <form onSubmit={submit} className="grid gap-5 lg:grid-cols-3">
                    <div className="space-y-5 lg:col-span-2">
                        <FormCard>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Field label="Instrumento" htmlFor="form_type">
                                    <NativeSelect id="form_type" value={template.key} onChange={(e) => changeTemplate(e.target.value)}>
                                        {templates.map((item) => (
                                            <option key={item.key} value={item.key}>
                                                {item.name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field label="Paciente" htmlFor="patient_id" error={errors.patient_id}>
                                    <NativeSelect
                                        id="patient_id"
                                        value={data.patient_id}
                                        onChange={(e) => setData('patient_id', e.target.value)}
                                        required
                                    >
                                        {patients.map((patient) => (
                                            <option key={patient.id} value={patient.id}>
                                                {patient.full_name}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </Field>
                            </div>
                        </FormCard>

                        <FormCard>
                            {template.fields.map((field, index) => {
                                const fieldError = (errors as Record<string, string>)[`answers.${field.key}`];
                                const value = data.answers[field.key] ?? '';
                                const update = (newValue: string) => setData('answers', { ...data.answers, [field.key]: newValue });

                                return (
                                    <Field
                                        key={field.key}
                                        label={`${index + 1}. ${field.label}`}
                                        htmlFor={field.key}
                                        error={fieldError}
                                        hint={field.optional ? 'Opcional' : undefined}
                                    >
                                        {field.type === 'select' && (
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {field.options?.map((option) => (
                                                    <button
                                                        key={option.value}
                                                        type="button"
                                                        onClick={() => update(option.value)}
                                                        className={cn(
                                                            'focus-ring flex items-center justify-between gap-2 rounded-lg border px-3.5 py-2.5 text-left text-sm transition-all duration-200',
                                                            value === option.value
                                                                ? 'border-ring bg-primary-soft text-accent-foreground font-semibold shadow-xs'
                                                                : 'border-border bg-card hover:border-ring/40 hover:bg-muted/60',
                                                        )}
                                                    >
                                                        <span>{option.label}</span>
                                                        {option.score !== undefined && option.score > 0 && (
                                                            <span className="tabular text-muted-foreground text-xs">+{option.score}</span>
                                                        )}
                                                    </button>
                                                ))}
                                            </div>
                                        )}

                                        {field.type === 'number' && (
                                            <Input
                                                id={field.key}
                                                type="number"
                                                inputMode="decimal"
                                                min={field.min}
                                                max={field.max}
                                                value={value}
                                                onChange={(e) => update(e.target.value)}
                                                required={!field.optional}
                                            />
                                        )}

                                        {/* La fecha del examen no es la de captura: un resultado
                                            puede cargarse días después de la toma de la muestra. */}
                                        {field.type === 'date' && (
                                            <Input
                                                id={field.key}
                                                type="date"
                                                value={value}
                                                onChange={(e) => update(e.target.value)}
                                                required={!field.optional}
                                            />
                                        )}

                                        {field.type === 'textarea' && (
                                            <Textarea
                                                id={field.key}
                                                value={value}
                                                onChange={(e) => update(e.target.value)}
                                                required={!field.optional}
                                            />
                                        )}
                                    </Field>
                                );
                            })}
                        </FormCard>
                    </div>

                    <div className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                        {livePreview && (
                            <div
                                className={cn(
                                    'rounded-xl border p-5 transition-colors duration-300',
                                    livePreview.threshold && livePreview.complete
                                        ? toneStyles[livePreview.threshold.tone]
                                        : 'border-border bg-muted/40 text-muted-foreground',
                                )}
                            >
                                <div className="flex items-center gap-2">
                                    <Sparkles className="size-4" />
                                    <p className="text-xs font-bold tracking-wide uppercase">Interpretación</p>
                                </div>

                                <p className="tabular mt-3 text-4xl font-extrabold">{livePreview.total}</p>

                                {livePreview.complete && livePreview.threshold ? (
                                    <>
                                        <p className="mt-1 text-sm font-bold">{livePreview.threshold.label}</p>
                                        <p className="mt-2 text-xs leading-relaxed opacity-90">{livePreview.threshold.advice}</p>
                                    </>
                                ) : (
                                    <p className="mt-1 text-sm">Responde todas las preguntas para ver la interpretación.</p>
                                )}
                            </div>
                        )}

                        <FormCard className="space-y-3">
                            <Button className="w-full" disabled={processing || patients.length === 0}>
                                Guardar formulario
                            </Button>
                            <Button type="button" variant="ghost" className="w-full" asChild>
                                <Link href={route('medico.formularios-clinicos.index')}>Cancelar</Link>
                            </Button>
                            {patients.length === 0 && (
                                <p className="text-muted-foreground text-center text-xs">Registra un paciente antes de aplicar instrumentos.</p>
                            )}
                        </FormCard>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
