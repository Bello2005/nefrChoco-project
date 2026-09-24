import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Building2, ClipboardList, Network, Stethoscope, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Preparación para interoperar', href: '/admin/interoperabilidad' },
];

interface MissingRow {
    id: number;
    name: string;
    missing: string[];
}

interface Props {
    patients: { count: number; items: MissingRow[] };
    practitioners: { count: number; items: MissingRow[] };
    institution: string[];
    attentions: { count: number; items: { id: number; patientName: string | null; doctorName: string | null; scheduledAt: string }[] };
}

function MissingList({ rows, href, empty }: { rows: MissingRow[]; href: (id: number) => string; empty: string }) {
    if (rows.length === 0) {
        return <p className="text-muted-foreground text-sm">{empty}</p>;
    }

    return (
        <ul className="divide-border/70 divide-y">
            {rows.map((row) => (
                <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                    <Link href={href(row.id)} className="text-sm font-semibold hover:underline">
                        {row.name}
                    </Link>
                    <div className="flex flex-wrap gap-1">
                        {row.missing.map((field) => (
                            <Badge key={field} variant="warning">
                                {field}
                            </Badge>
                        ))}
                    </div>
                </li>
            ))}
        </ul>
    );
}

/**
 * Qué falta para generar RDA y RIPS. Sin contenido clínico: solo nombres,
 * qué campo falta y un enlace para completarlo.
 */
export default function Interoperabilidad({ patients, practitioners, institution, attentions }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Preparación para interoperar" />

            <div className="space-y-6">
                <PageHeader
                    title="Preparación para interoperar"
                    description="Lo que falta completar antes de enviar resúmenes de atención (RDA) o generar RIPS."
                    icon={Network}
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Pacientes con datos faltantes" value={patients.count} icon={Users} tone="primary" />
                    <StatCard label="Médicos por completar" value={practitioners.count} icon={Stethoscope} tone="info" />
                    <StatCard label="Datos de la IPS faltantes" value={institution.length} icon={Building2} tone="brand" />
                    <StatCard label="Atenciones sin diagnóstico principal" value={attentions.count} icon={ClipboardList} tone="success" />
                </div>

                <div className="grid gap-5 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Pacientes</CardTitle>
                            <p className="text-muted-foreground text-sm">
                                Documento, primer nombre, primer apellido, sexo biológico, DIVIPOLA y EAPB.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <MissingList
                                rows={patients.items}
                                href={(id) => route('fichas-por-revisar.edit', id)}
                                empty="Todas las fichas tienen los datos que exige el IHCE."
                            />
                            {patients.count > patients.items.length && (
                                <p className="text-muted-foreground mt-3 text-xs">
                                    Se muestran {patients.items.length} de {patients.count}.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Médicos</CardTitle>
                            <p className="text-muted-foreground text-sm">Perfil profesional completo y verificación en RETHUS.</p>
                        </CardHeader>
                        <CardContent>
                            <MissingList
                                rows={practitioners.items}
                                href={(id) => route('admin.usuarios.edit', id)}
                                empty="Todos los médicos tienen el perfil completo y verificado."
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Institución</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {institution.length === 0 ? (
                                <p className="text-muted-foreground">La IPS tiene código REPS y de sede.</p>
                            ) : (
                                <>
                                    <div className="flex flex-wrap gap-1">
                                        {institution.map((field) => (
                                            <Badge key={field} variant="warning">
                                                {field}
                                            </Badge>
                                        ))}
                                    </div>
                                    <Link href={route('admin.institucion.index')} className="text-primary font-semibold hover:underline">
                                        Ver datos de la institución
                                    </Link>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Atenciones cerradas sin diagnóstico principal</CardTitle>
                            <p className="text-muted-foreground text-sm">El médico de la cita lo agrega con una aclaración desde la historia.</p>
                        </CardHeader>
                        <CardContent>
                            {attentions.items.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Todas las atenciones cerradas tienen diagnóstico principal CIE-10.</p>
                            ) : (
                                <ul className="divide-border/70 divide-y">
                                    {attentions.items.map((row) => (
                                        <li
                                            key={row.id}
                                            className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm first:pt-0 last:pb-0"
                                        >
                                            <span className="font-semibold">{row.patientName ?? '—'}</span>
                                            <span className="text-muted-foreground text-xs">
                                                {formatDateTime(row.scheduledAt)} · {row.doctorName ?? '—'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
