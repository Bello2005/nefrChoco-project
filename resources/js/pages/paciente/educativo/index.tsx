import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { BookOpen, FileText, Film, Play } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Educación', href: '/paciente/educativo' }];

const typeConfig: Record<string, { label: string; icon: typeof Film; action: string }> = {
    video: { label: 'Video', icon: Film, action: 'Ver video' },
    pdf: { label: 'Guía PDF', icon: FileText, action: 'Abrir guía' },
    articulo: { label: 'Artículo', icon: BookOpen, action: 'Leer artículo' },
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
    filters: { categoria: string };
}

export default function EducativoIndex({ contents, categories, filters }: Props) {
    const applyFilter = (categoria: string) => {
        router.get(route('paciente.educativo.index'), categoria ? { categoria } : {}, { preserveState: true, replace: true });
    };

    const categoryLabel = (value: string) => categories.find((category) => category.value === value)?.label ?? value;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Educación en salud" />

            <div className="space-y-6">
                <PageHeader
                    title="Educación en salud"
                    description="Material preparado por tu equipo médico para acompañarte entre consultas."
                    icon={BookOpen}
                />

                <div className="flex flex-wrap gap-2">
                    <Button size="sm" variant={filters.categoria === '' ? 'default' : 'outline'} onClick={() => applyFilter('')}>
                        Todos
                    </Button>
                    {categories.map((category) => (
                        <Button
                            key={category.value}
                            size="sm"
                            variant={filters.categoria === category.value ? 'default' : 'outline'}
                            onClick={() => applyFilter(category.value)}
                        >
                            {category.label}
                        </Button>
                    ))}
                </div>

                {contents.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title="Sin contenido por ahora"
                        description="Tu IPS todavía no ha publicado material en esta categoría."
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
                                        <span className="bg-brand-soft text-brand flex size-11 items-center justify-center rounded-xl">
                                            <Icon className="size-5" />
                                        </span>
                                        <Badge variant="outline">{config.label}</Badge>
                                    </div>

                                    <h3 className="font-display mt-4 text-base font-bold">{content.title}</h3>
                                    <p className="text-muted-foreground mt-1.5 flex-1 text-sm">{content.description}</p>

                                    <Badge variant="accent" className="mt-4 self-start">
                                        {categoryLabel(content.ecnt_category)}
                                    </Badge>

                                    <Button className="mt-4 w-full" variant="outline" asChild>
                                        <a href={content.url_or_path} target="_blank" rel="noreferrer noopener">
                                            <Play />
                                            {config.action}
                                        </a>
                                    </Button>
                                </article>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
