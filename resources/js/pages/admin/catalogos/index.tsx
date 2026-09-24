import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Library } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Catálogos', href: '/admin/catalogos' },
];

interface SystemRow {
    key: string;
    name: string;
    version: string | null;
    source: string | null;
    sha256: string | null;
    importedAt: string | null;
    importedBy: string | null;
    activeCount: number;
    inactiveCount: number;
}

interface Props {
    systems: SystemRow[];
    expected: { key: string; name: string }[];
    searchMode: string;
}

const searchModeLabel: Record<string, string> = {
    pg_trgm: 'PostgreSQL con pg_trgm (búsqueda por parte del nombre, con índice)',
    ilike: 'PostgreSQL con índice normal (sin pg_trgm)',
    like: 'LIKE (entorno de pruebas)',
};

/**
 * Catálogos oficiales importados. Solo lectura: se importan con
 * `php artisan catalogos:importar` desde los archivos oficiales.
 */
export default function CatalogosIndex({ systems, expected, searchMode }: Props) {
    const imported = new Set(systems.map((system) => system.key));
    const missing = expected.filter((system) => !imported.has(system.key));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catálogos" />

            <div className="space-y-6">
                <PageHeader
                    title="Catálogos oficiales"
                    description="CIE-10, CIE-11, CUPS, DIVIPOLA, EAPB y demás catálogos importados de sus fuentes oficiales."
                    icon={Library}
                />

                {missing.length > 0 && (
                    <div className="border-warning/30 bg-warning-soft rounded-xl border p-4 text-sm">
                        <p className="font-semibold">Faltan por importar: {missing.map((system) => system.name).join(', ')}.</p>
                        <p className="text-muted-foreground mt-1">
                            Mientras falten, los campos que dependen de ellos se quedan como texto o sin validar. Cómo importarlos está en el manual
                            técnico.
                        </p>
                    </div>
                )}

                {systems.length === 0 ? (
                    <EmptyState icon={Library} title="Todavía no hay catálogos importados" />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Catálogo</TableHead>
                                <TableHead>Versión</TableHead>
                                <TableHead>Importado</TableHead>
                                <TableHead className="text-right">Vigentes</TableHead>
                                <TableHead className="text-right">Inactivos</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {systems.map((system) => (
                                <TableRow key={system.key}>
                                    <TableCell>
                                        <p className="font-semibold">{system.name}</p>
                                        <p className="text-muted-foreground text-xs">
                                            <code>{system.key}</code>
                                            {system.source ? ` · ${system.source}` : ''}
                                        </p>
                                        {system.sha256 && (
                                            <p className="text-muted-foreground font-mono text-[10px]" title="SHA-256 del archivo importado">
                                                {system.sha256.slice(0, 16)}…
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell>{system.version ?? <Badge variant="outline">Sin versión</Badge>}</TableCell>
                                    <TableCell className="text-sm">
                                        {system.importedAt ? formatDateTime(system.importedAt) : '—'}
                                        {system.importedBy && <p className="text-muted-foreground text-xs">{system.importedBy}</p>}
                                    </TableCell>
                                    <TableCell className="tabular text-right">{system.activeCount}</TableCell>
                                    <TableCell className="tabular text-muted-foreground text-right">{system.inactiveCount}</TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <p className="text-muted-foreground text-xs">Búsqueda: {searchModeLabel[searchMode] ?? searchMode}.</p>
            </div>
        </AppLayout>
    );
}
