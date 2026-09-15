import { EducationalContentFields } from '@/components/forms/educational-content-fields';
import { FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { BookPlus } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Contenido educativo', href: '/admin/educativo' },
    { title: 'Nuevo', href: '/admin/educativo/crear' },
];

export default function EducativoCreate({ categories }: { categories: { value: string; label: string }[] }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        type: 'articulo',
        url_or_path: '',
        ecnt_category: categories[0]?.value ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.educativo.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo contenido educativo" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Nuevo contenido educativo" description="Publica material de apoyo para los pacientes." icon={BookPlus} />

                <form onSubmit={submit}>
                    <FormCard>
                        <EducationalContentFields data={data} errors={errors} setData={setData} categories={categories} />

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing}>Publicar contenido</Button>
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
