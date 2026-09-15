import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, ExternalLink, FileText, Film, Plus } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Contenido educativo', href: '/admin/educativo' },
];

const typeConfig: Record<string, { label: string; icon: typeof Film }> = {
    video: { label: 'Video', icon: Film },
    pdf: { label: 'PDF', icon: FileText },
    articulo: { label: 'Artículo', icon: BookOpen },
};

interface Content {
    id: number;
    title: string;
    description: string | null;
    type: string;
    url_or_path: string;
    ecnt_category: string;
}

interface Props {
    contents: Content[];
    categories: { value: string; label: string }[];
}

export default function EducativoIndex({ contents, categories }: Props) {
    const categoryLabel = (value: string) => categories.find((category) => category.value === value)?.label ?? value;

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar este contenido educativo?')) {
            router.delete(route('admin.educativo.destroy', id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contenido educativo" />

            <div className="space-y-6">
                <PageHeader
                    title="Contenido educativo"
                    description="Material de promoción y prevención que verán los pacientes en su módulo educativo."
                    icon={BookOpen}
                    actions={
                        <Button asChild>
                            <Link href={route('admin.educativo.create')}>
                                <Plus />
                                Nuevo contenido
                            </Link>
                        </Button>
                    }
                />

                {contents.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title="Todavía no hay contenido"
                        description="Publica videos, guías en PDF o artículos para acompañar a los pacientes entre consultas."
                        action={
                            <Button asChild>
                                <Link href={route('admin.educativo.create')}>Publicar el primero</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {contents.map((content) => {
                            const config = typeConfig[content.type] ?? typeConfig.articulo;
                            const Icon = config.icon;

                            return (
                                <article
                                    key={content.id}
                                    className="bg-card border-border/70 flex flex-col rounded-xl border p-5 shadow-sm transition-shadow duration-300 hover:shadow-md"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <span className="bg-primary-soft text-accent-foreground flex size-10 items-center justify-center rounded-lg">
                                            <Icon className="size-5" />
                                        </span>
                                        <Badge variant="outline">{config.label}</Badge>
                                    </div>

                                    <h3 className="font-display mt-4 text-base font-bold">{content.title}</h3>
                                    <p className="text-muted-foreground mt-1.5 line-clamp-3 flex-1 text-sm">{content.description}</p>

                                    <Badge variant="accent" className="mt-4 self-start">
                                        {categoryLabel(content.ecnt_category)}
                                    </Badge>

                                    <div className="mt-4 flex flex-wrap items-center gap-2">
                                        <Button size="sm" variant="outline" asChild>
                                            <Link href={route('admin.educativo.edit', content.id)}>Editar</Link>
                                        </Button>
                                        <Button size="sm" variant="ghost" asChild>
                                            <a href={content.url_or_path} target="_blank" rel="noreferrer noopener">
                                                <ExternalLink />
                                                Abrir
                                            </a>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive hover:bg-destructive-soft"
                                            onClick={() => handleDelete(content.id)}
                                        >
                                            Eliminar
                                        </Button>
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
