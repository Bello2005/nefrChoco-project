import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { HeartHandshake, MonitorSmartphone, Phone, Pill, ShieldCheck, Siren, Stethoscope, Undo2, WifiOff, type LucideIcon } from 'lucide-react';
import { FormEventHandler, type ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis citas', href: '/paciente/mis-citas' },
    { title: 'Autorización', href: '#' },
];

interface Props {
    appointment: {
        id: number;
        scheduled_at: string;
        doctor: { id: number; name: string } | null;
    };
    version: string;
    contactEmail: string;
}

function ConsentItem({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children: ReactNode }) {
    return (
        <li className="flex items-start gap-3">
            <span className="bg-brand-soft text-brand-strong flex size-8 shrink-0 items-center justify-center rounded-lg">
                <Icon className="size-4" aria-hidden="true" />
            </span>
            <p>
                <span className="font-semibold">{title}</span> {children}
            </p>
        </li>
    );
}

export default function ConsentimientoTeleconsulta({ appointment, version, contactEmail }: Props) {
    const { data, setData, post, processing, errors } = useForm({ accepted: false as boolean });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('paciente.mis-citas.teleconsulta.consentimiento.store', appointment.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Autorizar la teleconsulta" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-start gap-3.5">
                    <span className="bg-primary-soft text-accent-foreground flex size-11 shrink-0 items-center justify-center rounded-xl">
                        <MonitorSmartphone className="size-5" aria-hidden="true" />
                    </span>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-extrabold">Antes de entrar a la videollamada</h1>
                        <p className="text-muted-foreground text-sm">
                            Tu cita con {appointment.doctor?.name ?? 'el equipo médico'} es el {formatDateTime(appointment.scheduled_at)}.
                        </p>
                    </div>
                </div>

                <div className="bg-card border-border/70 space-y-5 rounded-xl border p-6 shadow-sm">
                    <p className="text-sm">
                        Una teleconsulta es una atención médica de verdad, hecha por videollamada en vez de en el puesto de salud. Antes de empezar
                        necesitamos que sepas cómo funciona y que estés de acuerdo.
                    </p>

                    <ul className="space-y-4 text-sm">
                        {/* Res. 1644 de 2026, art. 7. Los textos nuevos son borradores cortos:
                            TODO: validar con la médica de la IPS y el área jurídica. */}
                        <ConsentItem icon={HeartHandshake} title="Lo que ganas.">
                            {/* TODO: validar con la médica de la IPS y el área jurídica. */}
                            Te evitas el viaje al puesto de salud y puedes tener tu control desde donde estés.
                        </ConsentItem>
                        <li className="flex items-start gap-3">
                            <span className="bg-brand-soft text-brand-strong flex size-8 shrink-0 items-center justify-center rounded-lg">
                                <Stethoscope className="size-4" aria-hidden="true" />
                            </span>
                            <p>
                                <span className="font-semibold">No hay examen físico.</span> Tu profesional no puede tocarte, auscultarte ni tomarte
                                muestras. Si hace falta, te va a pedir que vayas al puesto de salud.
                            </p>
                        </li>
                        <li className="flex items-start gap-3">
                            <span className="bg-brand-soft text-brand-strong flex size-8 shrink-0 items-center justify-center rounded-lg">
                                <WifiOff className="size-4" aria-hidden="true" />
                            </span>
                            <p>
                                <span className="font-semibold">La conexión puede cortarse.</span> Si eso pasa, vuelve a entrar a la sala. Si no se
                                logra, la IPS te contacta para reprogramar o atenderte presencialmente.
                            </p>
                        </li>
                        <li className="flex items-start gap-3">
                            <span className="bg-brand-soft text-brand-strong flex size-8 shrink-0 items-center justify-center rounded-lg">
                                <ShieldCheck className="size-4" aria-hidden="true" />
                            </span>
                            <p>
                                <span className="font-semibold">Lo que se hable queda en tu historia clínica.</span> La sala es privada y solo entran
                                tú y tu profesional. La videollamada no se graba.
                            </p>
                        </li>
                        <ConsentItem icon={ShieldCheck} title="Cuida tu privacidad.">
                            {/* TODO: validar con la médica de la IPS y el área jurídica. */}
                            Toda comunicación por internet tiene riesgos: alguien a tu lado puede escuchar, o tu teléfono puede estar en manos de otra
                            persona. Busca un lugar donde estés solo, usa audífonos si puedes y no compartas el enlace de la sala.
                        </ConsentItem>
                        <ConsentItem icon={Stethoscope} title="Lo que te pedimos.">
                            {/* TODO: validar con la médica de la IPS y el área jurídica. */}
                            Conéctate a la hora de tu cita, ten a mano tus medicamentos y tus últimas mediciones, y cuéntale a tu profesional cómo te
                            has sentido de verdad.
                        </ConsentItem>
                        <ConsentItem icon={Pill} title="Si te formulan algo.">
                            {/* TODO: validar con la médica de la IPS y el área jurídica. */}
                            Tu profesional te dirá durante la consulta si te formula medicamentos o exámenes, y la IPS te explicará cómo recibir la
                            fórmula.
                        </ConsentItem>
                        <ConsentItem icon={Phone} title="Cómo te contactamos.">
                            {/* TODO: validar con la médica de la IPS y el área jurídica. */}
                            Si la IPS necesita comunicarse contigo, usa los datos de contacto de tu ficha. Si cambias de número, avísale a la IPS.
                        </ConsentItem>
                        <ConsentItem icon={Siren} title="No es para urgencias.">
                            {/* TODO: validar con la médica de la IPS y el área jurídica. */}
                            Si te sientes muy mal o tienes una urgencia, no esperes la teleconsulta: ve al servicio de urgencias más cercano.
                        </ConsentItem>
                        <ConsentItem icon={Undo2} title="Puedes cambiar de opinión.">
                            {/* TODO: validar con la médica de la IPS y el área jurídica. */}
                            Puedes retirar esta autorización cuando quieras desde «Mis consentimientos». Si la retiras, te la volvemos a pedir antes
                            de tu próxima teleconsulta.
                        </ConsentItem>
                    </ul>

                    <p className="text-muted-foreground border-border/70 border-t pt-4 text-sm">
                        Puedes pedir atención presencial en cualquier momento, sin dar explicaciones y sin perder tu cita. Si tienes dudas escribe a{' '}
                        <span className="text-foreground font-medium">{contactEmail}</span>.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="accepted"
                            checked={data.accepted}
                            onCheckedChange={(checked) => setData('accepted', checked === true)}
                            aria-describedby="accepted-error"
                            className="mt-0.5"
                        />
                        <Label htmlFor="accepted" className="text-sm leading-relaxed font-normal">
                            Entiendo cómo funciona la teleconsulta y autorizo que me atiendan por videollamada.
                        </Label>
                    </div>

                    <InputError id="accepted-error" message={errors.accepted} />

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" size="lg" disabled={processing}>
                            Autorizar y entrar a la sala
                        </Button>
                        <span className="text-muted-foreground text-xs">Versión {version}</span>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
