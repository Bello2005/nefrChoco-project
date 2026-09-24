import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { UserRoundSearch } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Fichas por revisar', href: '/fichas-por-revisar' }];

interface Row {
    id: number;
    fullName: string;
    document: string;
    municipality: string | null;
    reasons: string[];
}

export const reasonLabels: Record<string, string> = {
    nombres: 'Separar nombres y apellidos',
    tipo_documento: 'Tipo de documento',
    municipio: 'Municipio',
    sexo_biologico: 'Sexo biológico',
};

/**
 * Fichas que no se pudieron completar sin adivinar (Res. 866 de 2021). Solo
 * datos de identidad: nada clínico.
 */
export default function FichasPorRevisar({ patients }: { patients: Row[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Fichas por revisar" />

            <div className="space-y-6">
                <PageHeader
                    title="Fichas por revisar"
                    description="Fichas antiguas a las que les falta un dato de identidad o en las que la plataforma no quiso adivinar. Revisa cada una con el documento del paciente."
                    icon={UserRoundSearch}
                />

                {patients.length === 0 ? (
                    <EmptyState
                        icon={UserRoundSearch}
                        title="No hay fichas pendientes"
                        description="Todas las fichas tienen su identidad completa."
                    />
                ) : (
                    <ul className="space-y-2.5">
                        {patients.map((patient) => (
                            <li
                                key={patient.id}
                                className="bg-card border-border/70 flex flex-col gap-3 rounded-xl border p-4 shadow-sm sm:flex-row sm:items-center"
                            >
                                <div className="min-w-0 sm:flex-1">
                                    <p className="truncate text-sm font-semibold">{patient.fullName}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {patient.document}
                                        {patient.municipality ? ` · ${patient.municipality}` : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    {patient.reasons.map((reason) => (
                                        <Badge key={reason} variant="warning">
                                            {reasonLabels[reason] ?? reason}
                                        </Badge>
                                    ))}
                                </div>
                                <Button size="sm" asChild>
                                    <Link href={route('fichas-por-revisar.edit', patient.id)}>Completar</Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
