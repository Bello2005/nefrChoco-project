import { Field } from '@/components/forms/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import { submitOrQueue } from '@/lib/submit-or-queue';
import { type SharedData } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, CloudOff } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

interface VitalSignCompanion {
    value: string;
    label: string;
    unit: string;
    min: number;
    max: number;
}

export interface VitalSignTypeOption {
    value: string;
    label: string;
    unit: string;
    min: number;
    max: number;
    /** La presión arterial se registra junto a su diastólica en un mismo envío. */
    companion?: VitalSignCompanion | null;
}

function localDateTimeValue(date: Date): string {
    const pad = (value: number) => value.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** 'alto', 'bajo' o null respecto del rango de referencia del tipo. */
function evaluarRango(raw: string, range: { min: number; max: number } | undefined): 'alto' | 'bajo' | null {
    if (!range || raw === '') return null;

    const numeric = Number(raw);
    if (Number.isNaN(numeric)) return null;

    if (numeric < range.min) return 'bajo';
    if (numeric > range.max) return 'alto';

    return null;
}

function AvisoFueraDeRango({ estado, etiqueta }: { estado: 'alto' | 'bajo'; etiqueta: string }) {
    return (
        <p
            className={`rounded-lg border px-3 py-2 text-xs font-medium ${
                estado === 'alto' ? 'border-destructive/30 bg-destructive-soft text-destructive' : 'border-warning/30 bg-warning-soft text-warning'
            }`}
        >
            {etiqueta} está por {estado === 'alto' ? 'encima' : 'debajo'} del rango de referencia. Verifica la medición antes de guardar.
        </p>
    );
}

export function VitalSignForm({ action, types }: { action: string; types: VitalSignTypeOption[] }) {
    const { isOnline, refreshPending } = useOfflineSync();
    const ownerId = usePage<SharedData>().props.auth.user.id;
    const [result, setResult] = useState<{ kind: 'enviada' | 'encolada' | 'error'; text: string } | null>(null);
    const [sending, setSending] = useState(false);

    // useForm se conserva por el estado y los errores por campo, pero el envío no
    // pasa por Inertia: necesita saber si falló la red para guardar en la cola.
    const { data, setData, errors, setError, clearErrors, reset } = useForm({
        type: types[0]?.value ?? '',
        value: '',
        value_diastolic: '',
        recorded_at: localDateTimeValue(new Date()),
        notes: '',
    });

    const selectedType = useMemo(() => types.find((type) => type.value === data.type), [types, data.type]);
    const companion = selectedType?.companion ?? null;

    // Aviso inmediato si la cifra queda fuera del rango de referencia, para que
    // quien digita pueda confirmar la medición antes de guardarla.
    const outOfRange = useMemo(() => evaluarRango(data.value, selectedType), [selectedType, data.value]);
    const diastolicOutOfRange = useMemo(() => (companion ? evaluarRango(data.value_diastolic, companion) : null), [companion, data.value_diastolic]);

    // Sin conexión el servidor no valida hasta la sincronización, así que un par
    // invertido se encolaría para ser rechazado horas después. Se corta acá.
    const pairError = useMemo(() => {
        if (!companion || data.value === '' || data.value_diastolic === '') return null;

        const sistolica = Number(data.value);
        const diastolica = Number(data.value_diastolic);
        if (Number.isNaN(sistolica) || Number.isNaN(diastolica)) return null;

        return diastolica >= sistolica ? 'La diastólica debe ser menor que la sistólica. Revisa si se intercambiaron las cifras.' : null;
    }, [companion, data.value, data.value_diastolic]);

    const changeType = (value: string) => {
        setData('type', value);
        setData('value_diastolic', '');
    };

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();
        setResult(null);

        if (pairError || sending) return;

        setSending(true);
        clearErrors();

        try {
            // Se intenta enviar aunque el teléfono diga «en línea»: si la red no pasa
            // datos, la medición queda en el dispositivo con su clave de idempotencia
            // y se envía sola cuando vuelva la señal. El par de presión viaja en un
            // mismo envío, así que nunca queda a medias.
            const outcome = await submitOrQueue({
                url: action,
                label: selectedType?.label ?? 'Medición',
                payload: data,
                ownerId,
                isOnline,
            });

            if (outcome.kind === 'rechazada') {
                for (const [field, message] of Object.entries(outcome.errors)) {
                    setError(field as keyof typeof data, message);
                }
                if (Object.keys(outcome.errors).length === 0 && outcome.message) {
                    setResult({ kind: 'error', text: outcome.message });
                }
                return;
            }

            reset('value', 'value_diastolic', 'notes');

            if (outcome.kind === 'encolada') {
                await refreshPending();
                setResult({ kind: 'encolada', text: 'Guardada en este dispositivo. Se enviará cuando vuelva la conexión.' });
                return;
            }

            setResult({ kind: 'enviada', text: 'Medición registrada correctamente.' });
            // Trae la serie con la medición nueva sin perder la posición en la pantalla.
            router.reload();
        } finally {
            setSending(false);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <Field label="Tipo de medición" htmlFor="type" error={errors.type}>
                <NativeSelect id="type" value={data.type} onChange={(e) => changeType(e.target.value)}>
                    {types.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </NativeSelect>
            </Field>

            {companion ? (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label={`Sistólica (${selectedType?.unit})`}
                        htmlFor="value"
                        error={errors.value}
                        hint={`Rango ${selectedType?.min} - ${selectedType?.max}`}
                    >
                        <Input
                            id="value"
                            type="text"
                            inputMode="decimal"
                            value={data.value}
                            // input type="number" nativo rechaza la coma decimal: en
                            // teclado en español, "1,2" queda como "12" y dispara el
                            // error de máximo del navegador sin que el usuario note
                            // por qué. Aquí se acepta coma o punto y se normaliza a
                            // punto, que es lo que espera la validación numérica.
                            onChange={(e) => setData('value', e.target.value.replace(',', '.'))}
                            required
                        />
                    </Field>

                    <Field
                        label={`Diastólica (${companion.unit})`}
                        htmlFor="value_diastolic"
                        error={errors.value_diastolic}
                        hint={`Rango ${companion.min} - ${companion.max}`}
                    >
                        <Input
                            id="value_diastolic"
                            type="text"
                            inputMode="decimal"
                            value={data.value_diastolic}
                            onChange={(e) => setData('value_diastolic', e.target.value.replace(',', '.'))}
                            required
                        />
                    </Field>
                </div>
            ) : (
                <Field
                    label={`Valor${selectedType ? ` (${selectedType.unit})` : ''}`}
                    htmlFor="value"
                    error={errors.value}
                    hint={selectedType ? `Rango de referencia: ${selectedType.min} - ${selectedType.max} ${selectedType.unit}` : undefined}
                >
                    <Input
                        id="value"
                        type="text"
                        inputMode="decimal"
                        value={data.value}
                        onChange={(e) => setData('value', e.target.value.replace(',', '.'))}
                        required
                    />
                </Field>
            )}

            {pairError && (
                <p className="border-destructive/30 bg-destructive-soft text-destructive rounded-lg border px-3 py-2 text-xs font-medium">
                    {pairError}
                </p>
            )}

            {outOfRange && <AvisoFueraDeRango estado={outOfRange} etiqueta={companion ? 'La sistólica' : 'Este valor'} />}
            {diastolicOutOfRange && <AvisoFueraDeRango estado={diastolicOutOfRange} etiqueta="La diastólica" />}

            <Field label="Fecha y hora" htmlFor="recorded_at" error={errors.recorded_at}>
                <Input
                    id="recorded_at"
                    type="datetime-local"
                    value={data.recorded_at}
                    onChange={(e) => setData('recorded_at', e.target.value)}
                    required
                />
            </Field>

            <Field label="Notas" htmlFor="notes" error={errors.notes} hint="Opcional: cómo te sentías, si tomaste el medicamento, etc.">
                <Textarea id="notes" className="min-h-20" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
            </Field>

            {result?.kind === 'encolada' && (
                <p
                    role="status"
                    className="border-info/30 bg-info-soft text-info flex items-start gap-2 rounded-lg border px-3 py-2 text-xs font-medium"
                >
                    <CloudOff className="mt-0.5 size-3.5 shrink-0" />
                    {result.text}
                </p>
            )}

            {result?.kind === 'enviada' && (
                <p
                    role="status"
                    className="border-success/30 bg-success-soft text-success flex items-start gap-2 rounded-lg border px-3 py-2 text-xs font-medium"
                >
                    <CheckCircle2 className="mt-0.5 size-3.5 shrink-0" />
                    {result.text}
                </p>
            )}

            {result?.kind === 'error' && (
                <p
                    role="alert"
                    className="border-destructive/30 bg-destructive-soft text-destructive rounded-lg border px-3 py-2 text-xs font-medium"
                >
                    {result.text}
                </p>
            )}

            <Button className="w-full" disabled={sending || pairError !== null}>
                {sending ? 'Guardando…' : isOnline ? 'Registrar medición' : 'Guardar sin conexión'}
            </Button>
        </form>
    );
}
