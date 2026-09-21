import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, WifiOff } from 'lucide-react';

interface Content {
    id: number;
    title: string;
    description: string | null;
    ecnt_category: string;
    bodyHtml: string;
    availableOffline: boolean;
}

/**
 * Estilos del material. El cuerpo llega como HTML generado por el servidor a
 * partir de Markdown, así que solo puede traer estas etiquetas.
 */
const bodyStyles = [
    'text-[15px] leading-relaxed',
    '[&>*+*]:mt-4',
    '[&_h2]:font-display [&_h2]:mt-8 [&_h2]:text-lg [&_h2]:font-extrabold',
    '[&_h3]:mt-6 [&_h3]:text-base [&_h3]:font-bold',
    '[&_p]:text-muted-foreground',
    '[&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5 [&_ul]:text-muted-foreground',
    '[&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ol]:pl-5 [&_ol]:text-muted-foreground',
    '[&_strong]:text-foreground [&_strong]:font-semibold',
    '[&_a]:text-accent-foreground [&_a]:underline',
].join(' ');

export default function EducativoShow({ content, categoryLabel }: { content: Content; categoryLabel: string }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Educación', href: '/paciente/educativo' },
        { title: content.title, href: `/paciente/educativo/${content.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={content.title} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title={content.title} description={content.description ?? undefined} icon={BookOpen} />

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{categoryLabel}</Badge>
                    {content.availableOffline && (
                        <Badge variant="success">
                            <WifiOff />
                            Disponible sin conexión
                        </Badge>
                    )}
                </div>

                <Card>
                    <CardContent className="pt-6">
                        {/*
                         * El HTML viene del servidor, generado con Markdown y con
                         * `html_input: strip`: no puede contener etiquetas escritas
                         * a mano, así que no hay HTML crudo que inyectar aquí.
                         */}
                        <div className={bodyStyles} dangerouslySetInnerHTML={{ __html: content.bodyHtml }} />
                    </CardContent>
                </Card>

                <Button variant="outline" asChild>
                    <Link href={route('paciente.educativo.index')}>
                        <ArrowLeft />
                        Volver a Educación
                    </Link>
                </Button>
            </div>
        </AppLayout>
    );
}
