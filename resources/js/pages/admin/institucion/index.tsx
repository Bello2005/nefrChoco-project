import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Datos de la institución', href: '/admin/institucion' },
];

interface Props {
    fields: { key: string; label: string; value: string | null }[];
    missing: string[];
}

/**
 * Identificación de la IPS para el RDA (REPS y sede). Solo lectura: los datos
 * se configuran en el servidor (INSTITUTION_* en el .env).
 */
export default function Institucion({ fields, missing }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Datos de la institución" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="Datos de la institución"
                    description="Con estos datos la historia clínica interoperable identifica a la IPS: razón social, NIT, habilitación REPS y sede."
                    icon={Building2}
                />

                {missing.length > 0 ? (
                    <div className="border-warning/30 bg-warning-soft rounded-xl border p-4 text-sm">
                        <p className="font-semibold">Faltan {missing.length} datos.</p>
                        <p className="text-muted-foreground mt-1">
                            Los entrega la IPS y se configuran en el servidor (variables INSTITUTION_* del archivo .env). Nunca se completan con
                            valores de prueba.
                        </p>
                    </div>
                ) : (
                    <div className="border-success/30 bg-success-soft rounded-xl border p-4 text-sm font-semibold">
                        Todos los datos están completos.
                    </div>
                )}

                <Card>
                    <CardContent>
                        <dl className="divide-border/70 divide-y">
                            {fields.map((field) => (
                                <div key={field.key} className="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0">
                                    <dt className="text-muted-foreground text-sm">{field.label}</dt>
                                    <dd className="text-sm font-semibold">{field.value ?? <Badge variant="warning">Falta</Badge>}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
