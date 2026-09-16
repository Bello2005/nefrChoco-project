import { JitsiMeeting } from '@/components/jitsi-meeting';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, initialsFrom } from '@/lib/format';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, MicVocal, ShieldCheck, Signal, WifiOff } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis citas', href: '/paciente/mis-citas' },
    { title: 'Teleconsulta', href: '#' },
];

interface Props {
    appointment: {
        id: number;
        scheduled_at: string;
        doctor: { id: number; name: string } | null;
    };
    roomName: string;
    jitsiDomain: string;
}

// El provider de conexión vive dentro de AppLayout, así que el aviso tiene que
// ser un componente hijo y no leerse desde el cuerpo de la página.
function OfflineNotice() {
    const { isOnline } = useOfflineSync();

    if (isOnline) {
        return null;
    }

    return (
        <div className="border-warning/30 bg-warning-soft flex items-start gap-3 rounded-xl border p-4">
            <WifiOff className="text-warning mt-0.5 size-5 shrink-0" />
            <p className="text-sm">Estás sin conexión. La videollamada necesita internet: acércate a un punto con señal y la sala cargará sola.</p>
        </div>
    );
}

export default function PacienteTeleconsultaShow({ appointment, roomName, jitsiDomain }: Props) {
    const { auth } = usePage<SharedData>().props;
    const doctorName = appointment.doctor?.name ?? 'Equipo médico';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Teleconsulta" />

            <div className="space-y-5">
                <div className="flex flex-wrap items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={route('paciente.mis-citas.index')} aria-label="Volver a mis citas">
                            <ArrowLeft />
                        </Link>
                    </Button>

                    <span className="bg-primary text-primary-foreground flex size-11 shrink-0 items-center justify-center rounded-xl text-sm font-extrabold">
                        {initialsFrom(doctorName)}
                    </span>

                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-xl font-extrabold">{doctorName}</h1>
                        <p className="text-muted-foreground text-sm">{formatDateTime(appointment.scheduled_at)}</p>
                    </div>

                    <Badge variant="accent">
                        <ShieldCheck />
                        Sala privada
                    </Badge>
                </div>

                <OfflineNotice />

                <div className="grid gap-5 xl:grid-cols-3">
                    <div className="xl:col-span-2">
                        <JitsiMeeting domain={jitsiDomain} roomName={roomName} displayName={auth.user.name} />
                        <p className="text-muted-foreground mt-3 text-center text-xs">
                            Esta sala es única para tu cita y nadie más puede entrar con el enlace.
                        </p>
                    </div>

                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>Si la señal está débil</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div className="flex items-start gap-3">
                                <span className="bg-brand-soft text-brand flex size-8 shrink-0 items-center justify-center rounded-lg">
                                    <MicVocal className="size-4" />
                                </span>
                                <p>
                                    <span className="font-semibold">Apaga tu cámara.</span> El audio consume mucho menos datos y es lo que tu
                                    profesional necesita para atenderte.
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <span className="bg-brand-soft text-brand flex size-8 shrink-0 items-center justify-center rounded-lg">
                                    <Signal className="size-4" />
                                </span>
                                <p>
                                    <span className="font-semibold">Si se corta, vuelve a entrar.</span> La sala sigue abierta durante toda la
                                    consulta y tu profesional te espera adentro.
                                </p>
                            </div>
                            <p className="text-muted-foreground border-border/70 border-t pt-4 text-xs">
                                Al terminar, tu profesional cierra la consulta y registra las notas en tu historia clínica.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
