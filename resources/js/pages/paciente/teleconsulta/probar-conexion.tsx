import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import {
    classifyConnection,
    type ConnectionIssue,
    type ConnectionLevel,
    type ConnectionMeasurement,
    type ConnectionThresholds,
    kbpsFrom,
} from '@/lib/connection-check';
import { formatDateTime } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Gauge, Loader2, Phone, SignalLow } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mis citas', href: '/paciente/mis-citas' },
    { title: 'Probar mi conexión', href: '#' },
];

// Archivo propio, servido por la misma app: la prueba no le habla a nadie más.
const TEST_FILE = '/connection-test.bin';
const LATENCY_URL = '/up';

interface Props {
    appointment: { id: number; scheduled_at: string };
    thresholds: ConnectionThresholds;
}

/** Tiempo de ida y vuelta: el mejor de tres, para no castigar un pico suelto. */
async function measureLatency(): Promise<number | null> {
    const samples: number[] = [];

    for (let i = 0; i < 3; i++) {
        const start = performance.now();
        try {
            await fetch(`${LATENCY_URL}?t=${Date.now()}-${i}`, { cache: 'no-store' });
            samples.push(performance.now() - start);
        } catch {
            // Un intento fallido no invalida los demás.
        }
    }

    return samples.length ? Math.round(Math.min(...samples)) : null;
}

async function measureDownload(): Promise<number | null> {
    const start = performance.now();
    try {
        const response = await fetch(`${TEST_FILE}?t=${Date.now()}`, { cache: 'no-store' });
        const body = await response.arrayBuffer();

        return kbpsFrom(body.byteLength, performance.now() - start);
    } catch {
        return null;
    }
}

/**
 * Pide permiso de cámara y micrófono y suelta las pistas enseguida: solo
 * interesa saber si están disponibles, no grabar nada.
 */
async function checkMedia(): Promise<{ camera: boolean | null; microphone: boolean | null }> {
    if (!navigator.mediaDevices?.getUserMedia) {
        return { camera: null, microphone: null };
    }

    const tryGet = async (constraints: MediaStreamConstraints) => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            stream.getTracks().forEach((track) => track.stop());
            return true;
        } catch {
            return false;
        }
    };

    if (await tryGet({ audio: true, video: true })) {
        return { camera: true, microphone: true };
    }

    // Si falla con los dos, se separa: con micrófono y sin cámara todavía hay consulta por audio.
    const microphone = await tryGet({ audio: true });
    const camera = await tryGet({ video: true });

    return { camera, microphone };
}

const results: Record<ConnectionLevel, { title: string; text: string; icon: typeof CheckCircle2; tone: string }> = {
    video: {
        title: 'Tu conexión está lista para la videollamada',
        text: 'Entra a la sala a la hora de tu cita. Si la imagen se congela, apaga tu cámara y sigue por audio.',
        icon: CheckCircle2,
        tone: 'bg-success-soft text-success',
    },
    solo_audio: {
        title: 'Mejor entra solo con audio',
        text: 'Tu conexión alcanza para hablar, pero el video se puede cortar. Al entrar a la sala apaga tu cámara: tu profesional te puede atender igual.',
        icon: SignalLow,
        tone: 'bg-warning-soft text-warning',
    },
    insuficiente: {
        title: 'Tu conexión no alcanza en este momento',
        text: 'Intenta desde otro lugar con mejor señal. Si no puedes, comunícate con la IPS para que te llamen por teléfono o reprogramen tu cita.',
        icon: Phone,
        tone: 'bg-destructive-soft text-destructive',
    },
};

const issueText: Record<ConnectionIssue, string> = {
    sin_internet: 'No logramos conectarnos: revisa que tengas datos o wifi.',
    sin_microfono: 'No pudimos usar tu micrófono. Revisa el permiso en tu navegador.',
    sin_camara: 'No pudimos usar tu cámara. Revisa el permiso en tu navegador.',
    lenta: 'Tu internet está lento.',
    demora: 'Tu conexión tarda en responder.',
};

export default function ProbarConexion({ appointment, thresholds }: Props) {
    const [running, setRunning] = useState(false);
    const [result, setResult] = useState<{ level: ConnectionLevel; issues: ConnectionIssue[] } | null>(null);

    const run = async () => {
        setRunning(true);
        setResult(null);

        const online = navigator.onLine;
        const media = await checkMedia();
        const latencyMs = online ? await measureLatency() : null;
        const downloadKbps = online ? await measureDownload() : null;

        const measurement: ConnectionMeasurement = { online, ...media, latencyMs, downloadKbps };
        const classified = classifyConnection(measurement, thresholds);

        setResult(classified);
        setRunning(false);

        // Solo el nivel viaja al servidor, para que tu médico lo vea antes de la cita.
        // Sin conexión no se puede guardar, y no pasa nada: el resultado ya está en pantalla.
        if (online) {
            router.post(route('paciente.mis-citas.probar-conexion.store', appointment.id), { level: classified.level }, { preserveScroll: true });
        }
    };

    const shown = result ? results[result.level] : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Probar mi conexión" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader
                    title="Probar mi conexión"
                    description={`Para tu teleconsulta del ${formatDateTime(appointment.scheduled_at)}.`}
                    icon={Gauge}
                />

                <div className="bg-card border-border/70 space-y-4 rounded-xl border p-6 text-sm shadow-sm">
                    <p>
                        Revisamos tu internet, tu cámara y tu micrófono para saber si puedes hacer la videollamada. Tarda unos segundos y usa muy
                        pocos datos. Si el navegador te pregunta por la cámara y el micrófono, toca <strong>Permitir</strong>.
                    </p>
                    <p className="text-muted-foreground text-xs">No grabamos nada: solo revisamos que funcionen.</p>
                    <Button size="lg" onClick={run} disabled={running}>
                        {running ? <Loader2 className="animate-spin" /> : <Gauge />}
                        {running ? 'Probando…' : result ? 'Probar otra vez' : 'Empezar la prueba'}
                    </Button>
                </div>

                {shown && result && (
                    <div className="bg-card border-border/70 space-y-3 rounded-xl border p-6 shadow-sm" role="status" aria-live="polite">
                        <div className="flex items-start gap-3">
                            <span className={`flex size-10 shrink-0 items-center justify-center rounded-xl ${shown.tone}`}>
                                <shown.icon className="size-5" aria-hidden="true" />
                            </span>
                            <div className="space-y-1">
                                <h2 className="text-base font-bold">{shown.title}</h2>
                                <p className="text-sm">{shown.text}</p>
                            </div>
                        </div>
                        {result.issues.length > 0 && (
                            <ul className="text-muted-foreground list-disc space-y-1 pl-6 text-sm">
                                {result.issues.map((issue) => (
                                    <li key={issue}>{issueText[issue]}</li>
                                ))}
                            </ul>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={route('paciente.mis-citas.index')}>Volver a mis citas</Link>
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
