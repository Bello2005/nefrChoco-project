import { EducationalContentFields } from '@/components/forms/educational-content-fields';
import { FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Contenido educativo', href: '/admin/educativo' },
    { title: 'Editar', href: '#' },
];

interface Content {
    id: number;
    title: string;
    description: string | null;
    type: string;
    url_or_path: string;
    ecnt_category: string;
}

export default function EducativoEdit({ content, categories }: { content: Content; categories: { value: string; label: string }[] }) {
    const { data, setData, put, processing, errors } = useForm({
        title: content.title,
        description: content.description ?? '',
        type: content.type,
        url_or_path: content.url_or_path,
        ecnt_category: content.ecnt_category,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.educativo.update', content.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${content.title}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Editar contenido" description="Actualiza el material disponible para los pacientes." icon={BookOpen} />

                <form onSubmit={submit}>
                    <FormCard>
                        <EducationalContentFields data={data} errors={errors} setData={setData} categories={categories} />

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing}>Guardar cambios</Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('admin.educativo.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </FormCard>
                </form>
            </div>
        </AppLayout>
    );
}
