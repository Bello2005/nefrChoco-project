import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FileLock2, MonitorSmartphone, ShieldCheck } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mis consentimientos', href: '/paciente/mis-consentimientos' }];

interface Props {
    dataConsent: { acceptedAt: string | null; version: string | null } | null;
    teleconsultationConsent: { acceptedAt: string | null; revokedAt: string | null; isCurrent: boolean } | null;
    contactEmail: string;
}

/**
 * Lo que el paciente autorizó y cómo retirarlo (Res. 1644 de 2026, art. 7).
 *
 * La teleconsulta se retira con un botón porque solo afecta la modalidad de
 * atención. La autorización de datos no: la historia clínica se conserva por
 * ley aunque la persona la retire, así que ese camino pasa por la IPS.
 */
export default function MisConsentimientos({ dataConsent, teleconsultationConsent, contactEmail }: Props) {
    const revoke = () => {
        if (
            confirm(
                '¿Retirar tu autorización para la teleconsulta? No podrás entrar a una videollamada hasta que vuelvas a aceptar. Tus citas no se cancelan.',
            )
        ) {
            router.post(route('paciente.mis-consentimientos.teleconsulta.retirar'));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis consentimientos" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="Mis consentimientos"
                    description="Lo que autorizaste, cuándo lo hiciste y cómo cambiar de opinión."
                    icon={ShieldCheck}
                />

                <Card>
                    <CardHeader className="flex-row items-start justify-between space-y-0">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <MonitorSmartphone className="size-4.5" aria-hidden="true" />
                            Atención por videollamada
                        </CardTitle>
                        {teleconsultationConsent?.isCurrent ? (
                            <Badge variant="success">Vigente</Badge>
                        ) : teleconsultationConsent?.revokedAt ? (
                            <Badge variant="outline">Retirada</Badge>
                        ) : (
                            <Badge variant="outline">Sin autorizar</Badge>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        {teleconsultationConsent?.isCurrent ? (
                            <>
                                <p>
                                    La autorizaste el {formatDate(teleconsultationConsent.acceptedAt!)}. Puedes retirarla cuando quieras: no tienes
                                    que dar explicaciones y tus citas no se cancelan.
                                </p>
                                <Button variant="outline" className="text-destructive hover:bg-destructive-soft" onClick={revoke}>
                                    Retirar mi consentimiento
                                </Button>
                            </>
                        ) : teleconsultationConsent?.revokedAt ? (
                            <p>
                                La retiraste el {formatDate(teleconsultationConsent.revokedAt)}. Si más adelante tienes una teleconsulta, te la
                                volvemos a pedir antes de entrar a la sala.
                            </p>
                        ) : (
                            <p>Todavía no la has autorizado, o cambió el texto. Te la pedimos antes de tu próxima teleconsulta.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-start justify-between space-y-0">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileLock2 className="size-4.5" aria-hidden="true" />
                            Tratamiento de tus datos
                        </CardTitle>
                        {dataConsent?.acceptedAt && <Badge variant="success">Vigente</Badge>}
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        {dataConsent?.acceptedAt && <p>La autorizaste el {formatDate(dataConsent.acceptedAt)}.</p>}
                        {/* TODO: validar con el área jurídica de la IPS. */}
                        <p>
                            Tu historia clínica se conserva por el tiempo que exige la ley (Res. 839 de 2017), aunque retires esta autorización: es el
                            registro de la atención que recibiste y la IPS está obligada a guardarlo.
                        </p>
                        {/* TODO: validar con el área jurídica de la IPS. */}
                        <p>
                            Si quieres retirar esta autorización, o pedir que corrijan tus datos, escribe a{' '}
                            <a href={`mailto:${contactEmail}`} className="text-foreground font-semibold underline underline-offset-4">
                                {contactEmail}
                            </a>
                            . La IPS te responde y te explica qué datos puede dejar de usar y cuáles debe conservar.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
