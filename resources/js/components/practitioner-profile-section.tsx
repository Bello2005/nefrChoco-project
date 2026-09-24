import { CodeSelect } from '@/components/forms/code-select';
import { Field, FormCard } from '@/components/forms/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { useForm } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import { type FormEventHandler } from 'react';

export interface PractitionerData {
    documentType: string | null;
    documentNumber: string | null;
    profession: string | null;
    professionalRegistration: string | null;
    specialty: string | null;
    missing: string[];
    rethusVerifiedAt: string | null;
    rethusVerifiedBy: string | null;
    rethusNote: string | null;
    documentTypeCatalog: string | null;
    documentTypeLabel: string | null;
}

/**
 * Datos profesionales del médico y su verificación en RETHUS.
 *
 * La verificación la hace una persona en la consulta pública de ReTHUS; aquí
 * solo se deja constancia de quién, cuándo y qué anotó. No hay consulta
 * automática.
 */
export function PractitionerProfileSection({ userId, practitioner }: { userId: number; practitioner: PractitionerData }) {
    const profile = useForm({
        document_type: practitioner.documentType ?? 'CC',
        document_number: practitioner.documentNumber ?? '',
        profession: practitioner.profession ?? '',
        professional_registration: practitioner.professionalRegistration ?? '',
        specialty: practitioner.specialty ?? '',
    });

    const verification = useForm({ rethus_note: '' });

    const saveProfile: FormEventHandler = (e) => {
        e.preventDefault();
        profile.put(route('admin.usuarios.perfil-profesional', userId), { preserveScroll: true });
    };

    const verify: FormEventHandler = (e) => {
        e.preventDefault();
        verification.post(route('admin.usuarios.verificar-rethus', userId), {
            preserveScroll: true,
            onSuccess: () => verification.reset(),
        });
    };

    const canVerify = practitioner.missing.length === 0;

    return (
        <div className="space-y-4">
            <form onSubmit={saveProfile}>
                <FormCard>
                    <div>
                        <h2 className="text-base font-bold">Datos profesionales</h2>
                        <p className="text-muted-foreground text-sm">Los pide la historia clínica interoperable para identificar a quien atiende.</p>
                    </div>

                    {practitioner.missing.length > 0 && (
                        <p className="bg-warning-soft text-warning rounded-lg px-3 py-2 text-sm font-medium">
                            Falta: {practitioner.missing.join(', ')}. El médico puede seguir atendiendo mientras tanto.
                        </p>
                    )}

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="Tipo de documento" htmlFor="pp_document_type" error={profile.errors.document_type}>
                            {practitioner.documentTypeCatalog ? (
                                <CodeSelect
                                    id="pp_document_type"
                                    system={practitioner.documentTypeCatalog}
                                    value={profile.data.document_type}
                                    label={practitioner.documentTypeLabel}
                                    onChange={(code) => profile.setData('document_type', code)}
                                />
                            ) : (
                                <Input
                                    id="pp_document_type"
                                    value={profile.data.document_type}
                                    onChange={(e) => profile.setData('document_type', e.target.value)}
                                />
                            )}
                        </Field>
                        <Field label="Número de documento" htmlFor="pp_document_number" error={profile.errors.document_number}>
                            <Input
                                id="pp_document_number"
                                inputMode="numeric"
                                value={profile.data.document_number}
                                onChange={(e) => profile.setData('document_number', e.target.value)}
                            />
                        </Field>
                        <Field label="Profesión" htmlFor="pp_profession" error={profile.errors.profession}>
                            <Input
                                id="pp_profession"
                                value={profile.data.profession}
                                onChange={(e) => profile.setData('profession', e.target.value)}
                            />
                        </Field>
                        <Field label="Registro profesional" htmlFor="pp_registration" error={profile.errors.professional_registration}>
                            <Input
                                id="pp_registration"
                                value={profile.data.professional_registration}
                                onChange={(e) => profile.setData('professional_registration', e.target.value)}
                            />
                        </Field>
                        <Field label="Especialidad" htmlFor="pp_specialty" error={profile.errors.specialty} hint="Si no tiene, déjalo vacío.">
                            <Input id="pp_specialty" value={profile.data.specialty} onChange={(e) => profile.setData('specialty', e.target.value)} />
                        </Field>
                    </div>

                    <p className="text-muted-foreground text-xs">
                        Si cambias el documento o el registro profesional, la verificación en RETHUS se borra y hay que hacerla de nuevo.
                    </p>

                    <Button disabled={profile.processing}>Guardar datos profesionales</Button>
                </FormCard>
            </form>

            <FormCard>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-base font-bold">Verificación en RETHUS</h2>
                    {practitioner.rethusVerifiedAt ? (
                        <Badge variant="success">
                            <BadgeCheck />
                            Verificado
                        </Badge>
                    ) : (
                        <Badge variant="warning">Sin verificar</Badge>
                    )}
                </div>

                {practitioner.rethusVerifiedAt && (
                    <p className="text-sm">
                        Verificado por {practitioner.rethusVerifiedBy ?? '—'} el {formatDateTime(practitioner.rethusVerifiedAt)}.
                        {practitioner.rethusNote && <span className="text-muted-foreground block">Nota: {practitioner.rethusNote}</span>}
                    </p>
                )}

                <p className="text-muted-foreground text-sm">
                    Busca al médico en la consulta pública de ReTHUS con su documento. Si aparece activo, anota lo que viste y márcalo como
                    verificado.
                </p>

                <form onSubmit={verify} className="space-y-3">
                    <Field label="Nota de la verificación" htmlFor="rethus_note" error={verification.errors.rethus_note}>
                        <Textarea
                            id="rethus_note"
                            rows={2}
                            value={verification.data.rethus_note}
                            onChange={(e) => verification.setData('rethus_note', e.target.value)}
                            placeholder="Por ejemplo: consulta del 24/09/2026, aparece activo como médico general."
                        />
                    </Field>
                    <Button variant="outline" disabled={verification.processing || !canVerify}>
                        Marcar como verificado en RETHUS
                    </Button>
                    {!canVerify && <p className="text-muted-foreground text-xs">Primero completa y guarda los datos profesionales.</p>}
                </form>
            </FormCard>
        </div>
    );
}
