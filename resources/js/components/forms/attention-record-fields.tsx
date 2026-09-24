import { CodeSelect } from '@/components/forms/code-select';
import { Field } from '@/components/forms/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { Plus, Trash2 } from 'lucide-react';

/**
 * "Registro de la atención" (RDA y RIPS): diagnósticos CIE-10 (y CIE-11 si
 * ese catálogo está importado), procedimientos CUPS, medicamentos, alergias,
 * motivo, finalidad y causa externa.
 *
 * Se llena una sola vez, al cerrar la atención. Después no se edita: se
 * corrige con una aclaración desde la historia clínica.
 */

export interface AttentionCatalogs {
    cie10: boolean;
    cie11: boolean;
    cups: boolean;
    diagnosisType: string | null;
    purpose: string | null;
    externalCause: string | null;
}

export type DiagnosisRow = {
    cie10_code: string;
    cie11_code: string;
    role: string;
    diagnosis_type: string;
};

export type AttentionData = {
    consultation_reason: string;
    purpose: string;
    external_cause: string;
    diagnoses: DiagnosisRow[];
    procedures: { cups_code: string; quantity: number }[];
    medications: { description: string; dose: string; frequency: string; code: string }[];
    no_known_allergies: boolean;
    allergies: { substance: string; allergy_type: string; status: string }[];
};

export function emptyAttention(): AttentionData {
    return {
        consultation_reason: '',
        purpose: '',
        external_cause: '',
        diagnoses: [{ cie10_code: '', cie11_code: '', role: 'principal', diagnosis_type: '' }],
        procedures: [],
        medications: [],
        no_known_allergies: false,
        allergies: [],
    };
}

/**
 * Quita las filas vacías antes de enviar: un diagnóstico sin código o un
 * medicamento sin descripción no es un dato, es un campo que no se usó.
 */
export function cleanAttention(data: AttentionData): AttentionData {
    return {
        ...data,
        diagnoses: data.diagnoses.filter((row) => row.cie10_code !== ''),
        procedures: data.procedures.filter((row) => row.cups_code !== ''),
        medications: data.medications.filter((row) => row.description.trim() !== ''),
        allergies: data.no_known_allergies ? [] : data.allergies.filter((row) => row.substance.trim() !== ''),
    };
}

interface Props {
    data: AttentionData;
    setData: <K extends keyof AttentionData>(key: K, value: AttentionData[K]) => void;
    errors: Record<string, string | undefined>;
    catalogs: AttentionCatalogs;
}

function CodedOrText({ id, system, value, onChange }: { id: string; system: string | null; value: string; onChange: (value: string) => void }) {
    return system ? (
        <CodeSelect id={id} system={system} value={value} onChange={(code) => onChange(code)} />
    ) : (
        <Input id={id} value={value} onChange={(e) => onChange(e.target.value)} />
    );
}

export function AttentionRecordFields({ data, setData, errors, catalogs }: Props) {
    const updateRow = <K extends 'diagnoses' | 'procedures' | 'medications' | 'allergies'>(
        key: K,
        index: number,
        patch: Partial<AttentionData[K][number]>,
    ) => {
        const rows = [...data[key]] as AttentionData[K];
        rows[index] = { ...rows[index], ...patch };
        setData(key, rows);
    };

    const removeRow = (key: 'diagnoses' | 'procedures' | 'medications' | 'allergies', index: number) => {
        setData(key, data[key].filter((_, i) => i !== index) as never);
    };

    return (
        <div className="space-y-5">
            <div>
                <h3 className="font-display text-base font-bold">Registro de la atención</h3>
                <p className="text-muted-foreground mt-1 text-xs">Queda en la historia y no se edita después del cierre.</p>
            </div>

            <Field label="Motivo de consulta" htmlFor="consultation_reason" error={errors.consultation_reason}>
                <Textarea
                    id="consultation_reason"
                    rows={2}
                    value={data.consultation_reason}
                    onChange={(e) => setData('consultation_reason', e.target.value)}
                />
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Finalidad" htmlFor="purpose" error={errors.purpose}>
                    <CodedOrText id="purpose" system={catalogs.purpose} value={data.purpose} onChange={(value) => setData('purpose', value)} />
                </Field>
                <Field label="Causa externa" htmlFor="external_cause" error={errors.external_cause}>
                    <CodedOrText
                        id="external_cause"
                        system={catalogs.externalCause}
                        value={data.external_cause}
                        onChange={(value) => setData('external_cause', value)}
                    />
                </Field>
            </div>

            <section className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <h4 className="text-sm font-semibold">Diagnósticos (CIE-10)</h4>
                    {catalogs.cie10 && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                setData('diagnoses', [...data.diagnoses, { cie10_code: '', cie11_code: '', role: 'relacionado', diagnosis_type: '' }])
                            }
                        >
                            <Plus />
                            Agregar
                        </Button>
                    )}
                </div>

                {!catalogs.cie10 ? (
                    <p className="bg-warning-soft text-warning rounded-lg px-3 py-2 text-xs font-medium">
                        El catálogo CIE-10 todavía no está importado: por ahora la atención se cierra sin diagnóstico codificado.
                    </p>
                ) : (
                    <>
                        {errors.diagnoses && <p className="text-destructive text-sm font-medium">{errors.diagnoses}</p>}
                        {data.diagnoses.map((row, index) => (
                            <div key={index} className="border-border/70 space-y-3 rounded-lg border p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <NativeSelect
                                        aria-label="Tipo de diagnóstico"
                                        value={row.role}
                                        onChange={(e) => updateRow('diagnoses', index, { role: e.target.value })}
                                        className="h-9 w-auto"
                                    >
                                        <option value="principal">Principal</option>
                                        <option value="relacionado">Relacionado</option>
                                    </NativeSelect>
                                    {data.diagnoses.length > 1 && (
                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            aria-label="Quitar diagnóstico"
                                            onClick={() => removeRow('diagnoses', index)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                                <Field htmlFor={`dx-${index}`} error={errors[`diagnoses.${index}.cie10_code`]}>
                                    <CodeSelect
                                        id={`dx-${index}`}
                                        system="cie10"
                                        value={row.cie10_code}
                                        onChange={(code) => updateRow('diagnoses', index, { cie10_code: code })}
                                        placeholder="Busca el diagnóstico CIE-10…"
                                    />
                                </Field>
                                {/* Codificación dual solo si el catálogo CIE-11 está importado. Sin equivalencias automáticas. */}
                                {catalogs.cie11 && (
                                    <Field label="CIE-11 (opcional)" htmlFor={`dx11-${index}`} error={errors[`diagnoses.${index}.cie11_code`]}>
                                        <CodeSelect
                                            id={`dx11-${index}`}
                                            system="cie11"
                                            value={row.cie11_code}
                                            onChange={(code) => updateRow('diagnoses', index, { cie11_code: code })}
                                        />
                                    </Field>
                                )}
                                <Field label="Tipo de diagnóstico" htmlFor={`dxtype-${index}`} error={errors[`diagnoses.${index}.diagnosis_type`]}>
                                    <CodedOrText
                                        id={`dxtype-${index}`}
                                        system={catalogs.diagnosisType}
                                        value={row.diagnosis_type}
                                        onChange={(value) => updateRow('diagnoses', index, { diagnosis_type: value })}
                                    />
                                </Field>
                            </div>
                        ))}
                    </>
                )}
            </section>

            {catalogs.cups && (
                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <h4 className="text-sm font-semibold">Procedimientos (CUPS)</h4>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setData('procedures', [...data.procedures, { cups_code: '', quantity: 1 }])}
                        >
                            <Plus />
                            Agregar
                        </Button>
                    </div>
                    {data.procedures.map((row, index) => (
                        <div key={index} className="flex items-start gap-2">
                            <div className="flex-1">
                                <Field htmlFor={`cups-${index}`} error={errors[`procedures.${index}.cups_code`]}>
                                    <CodeSelect
                                        id={`cups-${index}`}
                                        system="cups"
                                        value={row.cups_code}
                                        onChange={(code) => updateRow('procedures', index, { cups_code: code })}
                                    />
                                </Field>
                            </div>
                            <Input
                                aria-label="Cantidad"
                                type="number"
                                min={1}
                                max={99}
                                className="w-20"
                                value={row.quantity}
                                onChange={(e) => updateRow('procedures', index, { quantity: Number(e.target.value) })}
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label="Quitar procedimiento"
                                onClick={() => removeRow('procedures', index)}
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    ))}
                </section>
            )}

            <section className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <h4 className="text-sm font-semibold">Medicamentos</h4>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setData('medications', [...data.medications, { description: '', dose: '', frequency: '', code: '' }])}
                    >
                        <Plus />
                        Agregar
                    </Button>
                </div>
                {data.medications.map((row, index) => (
                    <div key={index} className="border-border/70 grid gap-2 rounded-lg border p-3 sm:grid-cols-3">
                        <Input
                            aria-label="Medicamento"
                            placeholder="Medicamento"
                            value={row.description}
                            onChange={(e) => updateRow('medications', index, { description: e.target.value })}
                            className="sm:col-span-3"
                        />
                        <Input
                            aria-label="Dosis"
                            placeholder="Dosis"
                            value={row.dose}
                            onChange={(e) => updateRow('medications', index, { dose: e.target.value })}
                        />
                        <Input
                            aria-label="Frecuencia"
                            placeholder="Frecuencia"
                            value={row.frequency}
                            onChange={(e) => updateRow('medications', index, { frequency: e.target.value })}
                        />
                        <Button type="button" variant="ghost" onClick={() => removeRow('medications', index)}>
                            <Trash2 />
                            Quitar
                        </Button>
                    </div>
                ))}
            </section>

            <section className="space-y-3">
                <h4 className="text-sm font-semibold">Alergias</h4>
                {/* El RDA distingue "no tiene" de "no se preguntó": dejarlo todo vacío es "no se preguntó". */}
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="no_known_allergies"
                        checked={data.no_known_allergies}
                        onCheckedChange={(checked) => setData('no_known_allergies', checked === true)}
                    />
                    <Label htmlFor="no_known_allergies" className="text-sm font-normal">
                        Preguntado: sin alergias conocidas
                    </Label>
                </div>
                {errors.allergies && <p className="text-destructive text-sm">{errors.allergies}</p>}
                {!data.no_known_allergies && (
                    <>
                        {data.allergies.map((row, index) => (
                            <div key={index} className="border-border/70 grid gap-2 rounded-lg border p-3 sm:grid-cols-3">
                                <Input
                                    aria-label="Sustancia"
                                    placeholder="Sustancia"
                                    value={row.substance}
                                    onChange={(e) => updateRow('allergies', index, { substance: e.target.value })}
                                    className="sm:col-span-3"
                                />
                                <Input
                                    aria-label="Tipo"
                                    placeholder="Tipo"
                                    value={row.allergy_type}
                                    onChange={(e) => updateRow('allergies', index, { allergy_type: e.target.value })}
                                />
                                <Input
                                    aria-label="Estado"
                                    placeholder="Estado"
                                    value={row.status}
                                    onChange={(e) => updateRow('allergies', index, { status: e.target.value })}
                                />
                                <Button type="button" variant="ghost" onClick={() => removeRow('allergies', index)}>
                                    <Trash2 />
                                    Quitar
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setData('allergies', [...data.allergies, { substance: '', allergy_type: '', status: '' }])}
                        >
                            <Plus />
                            Agregar alergia
                        </Button>
                    </>
                )}
            </section>
        </div>
    );
}
