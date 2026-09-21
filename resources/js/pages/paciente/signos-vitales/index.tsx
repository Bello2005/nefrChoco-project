import { EmptyState } from '@/components/empty-state';
import { VitalSignForm, type VitalSignTypeOption } from '@/components/forms/vital-sign-form';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { VitalSignSeries, type VitalSeries } from '@/components/vital-sign-series';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Armchair, Eye, HeartPulse, PencilLine, UserRoundX, WifiOff } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Signos vitales', href: '/paciente/signos-vitales' }];

/*
 * TODO: validar con la médica de la IPS.
 *
 * Estos cuatro textos son instrucciones clínicas dirigidas al paciente y NO los
 * ha revisado la profesional de la IPS. Están redactados a partir de la práctica
 * habitual de automedición, pero la conducta correcta (tiempo de reposo, qué
 * cifra anotar, cuándo repetir la toma) la define ella. No dar por buena esta
 * guía en producción sin esa revisión.
 */
const guideSteps = [
    {
        icon: Armchair,
        title: 'Antes de medirte',
        // TODO: validar con la médica de la IPS.
        description:
            'Siéntate y descansa unos minutos, con la espalda apoyada y los pies en el piso. Evita medirte justo después de caminar, fumar o tomar café.',
    },
    {
        icon: Eye,
        title: 'Dónde leer el valor',
        // TODO: validar con la médica de la IPS.
        description:
            'El aparato muestra el número en su pantalla. En el tensiómetro son dos cifras, la de arriba y la de abajo; anota las dos tal como aparecen.',
    },
    {
        icon: PencilLine,
        title: 'Cómo registrarlo',
        // TODO: validar con la médica de la IPS.
        description: 'Elige el tipo de medición en el formulario, escribe el número que viste y guarda. No redondees ni corrijas la cifra.',
    },
    {
        icon: WifiOff,
        title: 'Sirve sin señal',
        // TODO: validar con la médica de la IPS.
        description: 'Si no tienes internet, la medición se guarda en tu teléfono y se envía sola cuando vuelva la señal. No tienes que repetirla.',
    },
];

interface Props {
    series: VitalSeries[];
    types: VitalSignTypeOption[];
    hasProfile: boolean;
    showGuide: boolean;
}

export default function SignosVitalesIndex({ series, types, hasProfile, showGuide }: Props) {
    const dismissGuide = () => {
        router.post(route('paciente.signos-vitales.guia.descartar'), {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis signos vitales" />

            <div className="space-y-6">
                <PageHeader
                    title="Mis signos vitales"
                    description="Registra tus mediciones en casa. Tu equipo médico las revisa para hacerte seguimiento a distancia."
                    icon={HeartPulse}
                />

                {showGuide && hasProfile && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2.5">
                                <span className="bg-primary-soft text-accent-foreground flex size-9 items-center justify-center rounded-lg">
                                    <HeartPulse className="size-4.5" aria-hidden="true" />
                                </span>
                                <CardTitle>Cómo registrar tus mediciones</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <ul className="grid gap-4 sm:grid-cols-2">
                                {guideSteps.map((step) => (
                                    <li key={step.title} className="flex gap-3">
                                        <span className="bg-primary-soft text-accent-foreground flex size-9 shrink-0 items-center justify-center rounded-lg">
                                            <step.icon className="size-4.5" aria-hidden="true" />
                                        </span>
                                        <div>
                                            <p className="text-sm font-semibold">{step.title}</p>
                                            <p className="text-muted-foreground mt-1 text-sm leading-relaxed">{step.description}</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <Button variant="outline" onClick={dismissGuide}>
                                Entendido, no volver a mostrar
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {!hasProfile ? (
                    <EmptyState
                        icon={UserRoundX}
                        title="Tu cuenta aún no está vinculada"
                        description="Pídele a tu IPS que vincule tu usuario con tu ficha de paciente para comenzar a registrar mediciones."
                    />
                ) : (
                    <div className="grid gap-5 lg:grid-cols-3">
                        <div className="lg:col-span-2">
                            {series.length === 0 ? (
                                <EmptyState
                                    icon={HeartPulse}
                                    title="Todavía no tienes mediciones"
                                    description="Registra tu primera medición con el formulario de al lado y verás aquí tu evolución."
                                />
                            ) : (
                                <VitalSignSeries series={series} />
                            )}
                        </div>

                        <Card className="lg:sticky lg:top-24 lg:self-start">
                            <CardHeader>
                                <CardTitle>Nueva medición</CardTitle>
                                <p className="text-muted-foreground text-sm">Toma la medición con calma y en reposo.</p>
                            </CardHeader>
                            <CardContent>
                                <VitalSignForm action={route('paciente.signos-vitales.store')} types={types} />
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
