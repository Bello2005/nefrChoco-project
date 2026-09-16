import { Field } from '@/components/forms/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

export interface PatientFormData {
    full_name: string;
    document_type: string;
    document_number: string;
    birth_date: string;
    biological_sex: string;
    municipality: string;
    phone: string;
    emergency_contact_name: string;
    emergency_contact_phone: string;
}

export interface BiologicalSexOption {
    value: string;
    label: string;
}

const municipalities = ['Quibdó', 'Istmina', 'Condoto', 'Tadó', 'Nuquí', 'Bahía Solano', 'Riosucio', 'Acandí', 'Bojayá', 'El Carmen de Atrato'];

interface Props {
    data: PatientFormData;
    errors: Partial<Record<keyof PatientFormData, string>>;
    setData: (key: keyof PatientFormData, value: string) => void;
    biologicalSexOptions: BiologicalSexOption[];
}

export function PatientFields({ data, errors, setData, biologicalSexOptions }: Props) {
    return (
        <>
            <Field label="Nombre completo" htmlFor="full_name" error={errors.full_name}>
                <Input id="full_name" value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} required autoFocus />
            </Field>

            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Tipo de documento" htmlFor="document_type" error={errors.document_type}>
                    <NativeSelect id="document_type" value={data.document_type} onChange={(e) => setData('document_type', e.target.value)}>
                        <option value="CC">Cédula de ciudadanía</option>
                        <option value="TI">Tarjeta de identidad</option>
                        <option value="RC">Registro civil</option>
                        <option value="CE">Cédula de extranjería</option>
                    </NativeSelect>
                </Field>

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
                    hint="Se pide solo para calcular la función renal (TFGe). No es una pregunta sobre identidad de género."
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

            <div className="grid gap-5 sm:grid-cols-2">
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
            </div>

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
        </>
    );
}
