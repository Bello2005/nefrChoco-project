import { Field } from '@/components/forms/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import { enqueue } from '@/lib/offline-queue';
import { useForm } from '@inertiajs/react';
import { CloudOff } from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

export interface VitalSignTypeOption {
    value: string;
    label: string;
    unit: string;
    min: number;
    max: number;
}

function localDateTimeValue(date: Date): string {
    const pad = (value: number) => value.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function VitalSignForm({ action, types }: { action: string; types: VitalSignTypeOption[] }) {
    const { isOnline, refreshPending } = useOfflineSync();
    const [queuedMessage, setQueuedMessage] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        type: types[0]?.value ?? '',
        value: '',
        recorded_at: localDateTimeValue(new Date()),
        notes: '',
    });

    const selectedType = useMemo(() => types.find((type) => type.value === data.type), [types, data.type]);

    // Aviso inmediato si la cifra queda fuera del rango de referencia, para que
    // quien digita pueda confirmar la medición antes de guardarla.
    const outOfRange = useMemo(() => {
        if (!selectedType || data.value === '') return null;

        const numeric = Number(data.value);
        if (Number.isNaN(numeric)) return null;

        if (numeric < selectedType.min) return 'bajo';
        if (numeric > selectedType.max) return 'alto';

        return null;
    }, [selectedType, data.value]);

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();
        setQueuedMessage(null);

        // Sin conexión la medición no se pierde: se guarda en el dispositivo con
        // su clave de idempotencia y se envía sola cuando vuelve la señal.
        if (!isOnline) {
            await enqueue({
                url: action,
                label: selectedType?.label ?? 'Medición',
                payload: { ...data, client_uuid: crypto.randomUUID() },
            });

            await refreshPending();
            reset('value', 'notes');
            setQueuedMessage('Guardada en este dispositivo. Se enviará cuando vuelva la conexión.');

            return;
        }

        post(action, {
            preserveScroll: true,
            onSuccess: () => reset('value', 'notes'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <Field label="Tipo de medición" htmlFor="type" error={errors.type}>
                <NativeSelect id="type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                    {types.map((type) => (
                        <option key={type.value} value={type.value}>
                            {type.label}
                        </option>
                    ))}
                </NativeSelect>
            </Field>

            <Field
                label={`Valor${selectedType ? ` (${selectedType.unit})` : ''}`}
                htmlFor="value"
                error={errors.value}
                hint={selectedType ? `Rango de referencia: ${selectedType.min} - ${selectedType.max} ${selectedType.unit}` : undefined}
            >
                <Input
                    id="value"
                    type="number"
                    step="0.1"
                    inputMode="decimal"
                    value={data.value}
                    onChange={(e) => setData('value', e.target.value)}
                    required
                />
            </Field>

            {outOfRange && (
                <p
                    className={`rounded-lg border px-3 py-2 text-xs font-medium ${
                        outOfRange === 'alto'
                            ? 'border-destructive/30 bg-destructive-soft text-destructive'
                            : 'border-warning/30 bg-warning-soft text-warning'
                    }`}
                >
                    Este valor está por {outOfRange === 'alto' ? 'encima' : 'debajo'} del rango de referencia. Verifica la medición antes de guardar.
                </p>
            )}

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

            {queuedMessage && (
                <p className="border-info/30 bg-info-soft text-info flex items-start gap-2 rounded-lg border px-3 py-2 text-xs font-medium">
                    <CloudOff className="mt-0.5 size-3.5 shrink-0" />
                    {queuedMessage}
                </p>
            )}

            <Button className="w-full" disabled={processing}>
                {isOnline ? 'Registrar medición' : 'Guardar sin conexión'}
            </Button>
        </form>
    );
}
