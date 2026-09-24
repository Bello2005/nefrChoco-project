import { CodeSelect } from '@/components/forms/code-select';
import { Field } from '@/components/forms/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

/**
 * Formulario de la ficha del paciente (Res. 866 de 2021).
 *
 * Los datos codificados se eligen con buscador cuando su catálogo oficial ya
 * está importado; si todavía no lo está, se escriben como texto, igual que
 * antes. Así el registro de pacientes no se bloquea por un archivo pendiente.
 */

export type PatientFormData = {
    first_name: string;
    middle_name: string;
    first_surname: string;
    second_surname: string;
    document_type: string;
    document_number: string;
    birth_date: string;
    biological_sex: string;
    municipality: string;
    municipality_code: string;
    phone: string;
    emergency_contact_name: string;
    emergency_contact_phone: string;
    gender_identity: string;
    ethnicity: string;
    disability: string;
    occupation: string;
    residence_zone: string;
    eapb_code: string;
    affiliation_type: string;
};

export interface BiologicalSexOption {
    value: string;
    label: string;
}

/** Campo de la ficha → clave del catálogo importado, o null si todavía no hay catálogo. */
export type PatientCatalogs = Partial<Record<keyof PatientFormData, string | null>>;

export type PatientCodeLabels = Partial<Record<keyof PatientFormData, string>>;

type PatientSource = Partial<Record<keyof PatientFormData, string | null>> & { birth_date?: string | null };

export function patientFormFrom(patient?: PatientSource): PatientFormData {
    const value = (key: keyof PatientFormData) => (patient?.[key] ?? '') as string;

    return {
        first_name: value('first_name'),
        middle_name: value('middle_name'),
        first_surname: value('first_surname'),
        second_surname: value('second_surname'),
        document_type: patient ? value('document_type') : 'CC',
        document_number: value('document_number'),
        birth_date: (patient?.birth_date ?? '').slice(0, 10),
        // Vacío en las fichas anteriores a que el dato existiera.
        biological_sex: value('biological_sex'),
        municipality: value('municipality'),
        municipality_code: value('municipality_code'),
        phone: value('phone'),
        emergency_contact_name: value('emergency_contact_name'),
        emergency_contact_phone: value('emergency_contact_phone'),
        gender_identity: value('gender_identity'),
        ethnicity: value('ethnicity'),
        disability: value('disability'),
        occupation: value('occupation'),
        residence_zone: value('residence_zone'),
        eapb_code: value('eapb_code'),
        affiliation_type: value('affiliation_type'),
    };
}

const municipalities = ['Quibdó', 'Istmina', 'Condoto', 'Tadó', 'Nuquí', 'Bahía Solano', 'Riosucio', 'Acandí', 'Bojayá', 'El Carmen de Atrato'];

interface Props {
    data: PatientFormData;
    errors: Partial<Record<keyof PatientFormData, string>>;
    setData: (key: keyof PatientFormData, value: string) => void;
    biologicalSexOptions: BiologicalSexOption[];
    catalogs: PatientCatalogs;
    codeLabels?: PatientCodeLabels;
}

/** Un dato codificado: buscador si hay catálogo, texto si no. */
function CodedField({ field, label, hint, props }: { field: keyof PatientFormData; label: string; hint?: string; props: Props }) {
    const system = props.catalogs[field];

    return (
        <Field label={label} htmlFor={field} error={props.errors[field]} hint={hint}>
            {system ? (
                <CodeSelect
                    id={field}
                    system={system}
                    value={props.data[field]}
                    label={props.codeLabels?.[field]}
                    onChange={(code) => props.setData(field, code)}
                    invalid={Boolean(props.errors[field])}
                />
            ) : (
                <Input id={field} value={props.data[field]} onChange={(e) => props.setData(field, e.target.value)} />
            )}
        </Field>
    );
}

export function PatientFields(props: Props) {
    const { data, errors, setData, biologicalSexOptions, catalogs } = props;

    return (
        <>
            {/* El RDA exige que primer nombre y primer apellido coincidan con el registro nacional. */}
            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Primer nombre" htmlFor="first_name" error={errors.first_name}>
                    <Input id="first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} required autoFocus />
                </Field>
                <Field label="Segundo nombre" htmlFor="middle_name" error={errors.middle_name} hint="Si no tiene, déjalo vacío.">
                    <Input id="middle_name" value={data.middle_name} onChange={(e) => setData('middle_name', e.target.value)} />
                </Field>
                <Field label="Primer apellido" htmlFor="first_surname" error={errors.first_surname}>
                    <Input id="first_surname" value={data.first_surname} onChange={(e) => setData('first_surname', e.target.value)} required />
                </Field>
                <Field label="Segundo apellido" htmlFor="second_surname" error={errors.second_surname} hint="Si no tiene, déjalo vacío.">
                    <Input id="second_surname" value={data.second_surname} onChange={(e) => setData('second_surname', e.target.value)} />
                </Field>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                {catalogs.document_type ? (
                    <CodedField field="document_type" label="Tipo de documento" props={props} />
                ) : (
                    <Field label="Tipo de documento" htmlFor="document_type" error={errors.document_type}>
                        <NativeSelect id="document_type" value={data.document_type} onChange={(e) => setData('document_type', e.target.value)}>
                            <option value="CC">Cédula de ciudadanía</option>
                            <option value="TI">Tarjeta de identidad</option>
                            <option value="RC">Registro civil</option>
                            <option value="CE">Cédula de extranjería</option>
                        </NativeSelect>
                    </Field>
                )}

                <Field label="Número de documento" htmlFor="document_number" error={errors.document_number}>
                    <Input
                        id="document_number"
                        inputMode="numeric"
                        value={data.document_number}
                        onChange={(e) => setData('document_number', e.target.value)}
                        required
                    />
                </Field>
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Fecha de nacimiento" htmlFor="birth_date" error={errors.birth_date}>
                    <Input id="birth_date" type="date" value={data.birth_date} onChange={(e) => setData('birth_date', e.target.value)} required />
                </Field>

                <Field
                    label="Sexo biológico"
                    htmlFor="biological_sex"
                    error={errors.biological_sex}
                    hint="Se usa para calcular la función renal (TFGe). Con «Indeterminado» o «Desconocido» la TFGe no se calcula. No es la identidad de género."
                >
                    <NativeSelect
                        id="biological_sex"
                        value={data.biological_sex}
                        onChange={(e) => setData('biological_sex', e.target.value)}
                        required
                    >
                        <option value="">Selecciona una opción</option>
                        {biologicalSexOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
            </div>

            {catalogs.municipality_code ? (
                <CodedField field="municipality_code" label="Municipio" hint="Busca por nombre del municipio." props={props} />
            ) : (
                <Field label="Municipio" htmlFor="municipality" error={errors.municipality}>
                    <Input
                        id="municipality"
                        list="municipios-choco"
                        value={data.municipality}
                        onChange={(e) => setData('municipality', e.target.value)}
                        required
                    />
                    <datalist id="municipios-choco">
                        {municipalities.map((municipality) => (
                            <option key={municipality} value={municipality} />
                        ))}
                    </datalist>
                </Field>
            )}

            <Field label="Teléfono" htmlFor="phone" error={errors.phone}>
                <Input id="phone" inputMode="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} required />
            </Field>

            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Contacto de emergencia" htmlFor="emergency_contact_name" error={errors.emergency_contact_name}>
                    <Input
                        id="emergency_contact_name"
                        value={data.emergency_contact_name}
                        onChange={(e) => setData('emergency_contact_name', e.target.value)}
                    />
                </Field>

                <Field label="Teléfono de emergencia" htmlFor="emergency_contact_phone" error={errors.emergency_contact_phone}>
                    <Input
                        id="emergency_contact_phone"
                        inputMode="tel"
                        value={data.emergency_contact_phone}
                        onChange={(e) => setData('emergency_contact_phone', e.target.value)}
                    />
                </Field>
            </div>

            <div className="border-border/70 space-y-5 border-t pt-5">
                <div>
                    <h3 className="text-sm font-semibold">Datos del asegurador y sociodemográficos</h3>
                    <p className="text-muted-foreground text-xs">
                        Los pide la historia clínica interoperable. Si no los sabes todavía, puedes dejarlos vacíos.
                    </p>
                </div>
                <div className="grid gap-5 sm:grid-cols-2">
                    <CodedField field="eapb_code" label="EAPB (asegurador)" props={props} />
                    <CodedField field="affiliation_type" label="Tipo de afiliación" hint="Régimen o tipo de usuario." props={props} />
                    <CodedField field="residence_zone" label="Zona de residencia" props={props} />
                    <CodedField field="occupation" label="Ocupación" props={props} />
                    <CodedField field="ethnicity" label="Pertenencia étnica" props={props} />
                    <CodedField field="disability" label="Discapacidad" props={props} />
                    <CodedField field="gender_identity" label="Identidad de género" props={props} />
                </div>
            </div>
        </>
    );
}
