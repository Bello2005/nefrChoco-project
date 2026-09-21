import { Field } from '@/components/forms/field';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

export interface EducationalContentFormData {
    title: string;
    description: string;
    type: string;
    body: string;
    available_offline: boolean;
    url_or_path: string;
    ecnt_category: string;
}

interface Props {
    data: EducationalContentFormData;
    errors: Partial<Record<keyof EducationalContentFormData, string>>;
    setData: <K extends keyof EducationalContentFormData>(key: K, value: EducationalContentFormData[K]) => void;
    categories: { value: string; label: string }[];
    maxBodyCharacters: number;
}

export function EducationalContentFields({ data, errors, setData, categories, maxBodyCharacters }: Props) {
    const hasOwnBody = data.body.trim().length > 0;

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

            <Field
                label="Contenido"
                htmlFor="body"
                error={errors.body}
                hint={`Escríbelo aquí para que el paciente lo lea dentro de la aplicación y funcione sin señal. Admite Markdown: ## para títulos, ** ** para negrita y - para listas. Máximo ${maxBodyCharacters.toLocaleString('es-CO')} caracteres.`}
            >
                <Textarea id="body" rows={14} value={data.body} onChange={(e) => setData('body', e.target.value)} className="font-mono text-sm" />
            </Field>

            {hasOwnBody && (
                <label className="flex items-start gap-3">
                    <Checkbox
                        id="available_offline"
                        checked={data.available_offline}
                        onCheckedChange={(checked) => setData('available_offline', checked === true)}
                        className="mt-0.5"
                    />
                    <span className="text-sm">
                        <span className="font-semibold">Disponible sin conexión</span>
                        <span className="text-muted-foreground block">
                            Se descarga al teléfono del paciente cuando abre Educación con señal, y queda disponible después sin ella.
                        </span>
                    </span>
                </label>
            )}

            <Field
                label="Enlace"
                htmlFor="url_or_path"
                error={errors.url_or_path}
                hint={
                    hasOwnBody
                        ? 'Opcional cuando el contenido se escribe aquí.'
                        : 'Obligatorio si no escribes el contenido arriba. El material enlazado vive en otro sitio y no funciona sin señal.'
                }
            >
                <Input
                    id="url_or_path"
                    type="url"
                    placeholder="https://..."
                    value={data.url_or_path}
                    onChange={(e) => setData('url_or_path', e.target.value)}
                />
            </Field>
        </>
    );
}
