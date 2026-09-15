import { Field } from '@/components/forms/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

export interface EducationalContentFormData {
    title: string;
    description: string;
    type: string;
    url_or_path: string;
    ecnt_category: string;
}

interface Props {
    data: EducationalContentFormData;
    errors: Partial<Record<keyof EducationalContentFormData, string>>;
    setData: (key: keyof EducationalContentFormData, value: string) => void;
    categories: { value: string; label: string }[];
}

export function EducationalContentFields({ data, errors, setData, categories }: Props) {
    return (
        <>
            <Field label="Título" htmlFor="title" error={errors.title}>
                <Input id="title" value={data.title} onChange={(e) => setData('title', e.target.value)} required autoFocus />
            </Field>

            <Field label="Descripción" htmlFor="description" error={errors.description} hint="Explica en lenguaje sencillo de qué trata el material.">
                <Textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
            </Field>

            <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Tipo de contenido" htmlFor="type" error={errors.type}>
                    <NativeSelect id="type" value={data.type} onChange={(e) => setData('type', e.target.value)}>
                        <option value="articulo">Artículo</option>
                        <option value="video">Video</option>
                        <option value="pdf">PDF</option>
                    </NativeSelect>
                </Field>

                <Field label="Categoría ECNT" htmlFor="ecnt_category" error={errors.ecnt_category}>
                    <NativeSelect id="ecnt_category" value={data.ecnt_category} onChange={(e) => setData('ecnt_category', e.target.value)}>
                        {categories.map((category) => (
                            <option key={category.value} value={category.value}>
                                {category.label}
                            </option>
                        ))}
                    </NativeSelect>
                </Field>
            </div>

            <Field label="Enlace" htmlFor="url_or_path" error={errors.url_or_path} hint="Dirección donde el paciente podrá consultar el material.">
                <Input
                    id="url_or_path"
                    type="url"
                    placeholder="https://..."
                    value={data.url_or_path}
                    onChange={(e) => setData('url_or_path', e.target.value)}
                    required
                />
            </Field>
        </>
    );
}
