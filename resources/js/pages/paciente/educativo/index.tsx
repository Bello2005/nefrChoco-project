import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, FileText, Film, Play, Wifi, WifiOff } from 'lucide-react';
import { useEffect } from 'react';

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
    url_or_path: string | null;
    ecnt_category: string;
    hasOwnBody: boolean;
    availableOffline: boolean;
}

interface Props {
    contents: Content[];
    categories: { value: string; label: string }[];
    filters: { categoria: string };
}

export default function EducativoIndex({ contents, categories, filters }: Props) {
    // El material marcado para sin conexión se descarga al abrir la sección, que
    // es cuando sabemos que hay señal. Esperar a que el paciente toque cada
    // tarjeta sería descargarlo justo cuando ya no puede.
    useEffect(() => {
        const urls = contents.filter((content) => content.availableOffline).map((content) => `/paciente/educativo/${content.id}`);

        if (urls.length === 0 || !navigator.serviceWorker?.controller) {
            return;
        }

        navigator.serviceWorker.controller.postMessage({ type: 'PRECACHE_MATERIAL', urls });
    }, [contents]);

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
                                        <span className="bg-brand-soft text-brand-strong flex size-11 items-center justify-center rounded-xl">
                                            <Icon className="size-5" />
                                        </span>
                                        <Badge variant="outline">{config.label}</Badge>
                                    </div>

                                    <h3 className="font-display mt-4 text-base font-bold">{content.title}</h3>
                                    <p className="text-muted-foreground mt-1.5 flex-1 text-sm">{content.description}</p>

                                    <div className="mt-4 flex flex-wrap items-center gap-2">
                                        <Badge variant="accent">{categoryLabel(content.ecnt_category)}</Badge>
                                        {content.availableOffline ? (
                                            <Badge variant="success">
                                                <WifiOff />
                                                Sin conexión
                                            </Badge>
                                        ) : (
                                            // Sin cuerpo propio el material vive en otro sitio: decirlo
                                            // evita que el paciente lo toque sin señal y encuentre un error.
                                            <Badge variant="outline">
                                                <Wifi />
                                                Requiere conexión
                                            </Badge>
                                        )}
                                    </div>

                                    {content.hasOwnBody ? (
                                        <Button className="mt-4 w-full" variant="outline" asChild>
                                            <Link href={route('paciente.educativo.show', content.id)}>
                                                <BookOpen />
                                                Leer aquí
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button className="mt-4 w-full" variant="outline" asChild>
                                            <a href={content.url_or_path ?? '#'} target="_blank" rel="noreferrer noopener">
                                                <Play />
                                                {config.action}
                                            </a>
                                        </Button>
                                    )}
                                </article>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
