import { WifiOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Botones de la barra. La lista completa de Jitsi trae reacciones, encuestas,
 * pizarra y compartir enlace: en una consulta clínica estorban, y "compartir
 * enlace" abre una puerta que no queremos abierta.
 *
 * `videoquality` se queda a propósito. Bajar la calidad a mano es a veces la
 * diferencia entre sostener la consulta o perderla donde la señal es mala, y
 * la guía del paciente ya le dice que apague la cámara si la señal está débil.
 */
const TOOLBAR_BUTTONS = ['microphone', 'camera', 'desktop', 'videoquality', 'tileview', 'settings', 'fullscreen', 'hangup'];

const CONFIG_OVERWRITE = {
    // La sala se abre desde la agenda, con la cita a la vista: la pantalla
    // previa de Jitsi solo agrega un paso donde el paciente puede trabarse.
    prejoinPageEnabled: false,

    // Invitar a alguien más a una atención médica no debería estar a un clic.
    disableInviteFunctions: true,

    // Sin llamadas a Gravatar ni a otros servicios de terceros con datos del
    // usuario: no hay razón para filtrar identidad por un costado, sea cual
    // sea el dominio de Jitsi configurado en `domain`.
    disableThirdPartyRequests: true,

    // No deja el nombre de la sala en el almacenamiento del navegador. El
    // teléfono del paciente puede ser compartido.
    doNotStoreRoom: true,

    // Evita el salto a "abrir en la app de Jitsi" en móviles, que sacaría al
    // paciente de la plataforma a mitad de la consulta.
    disableDeepLinking: true,
};

const INTERFACE_CONFIG_OVERWRITE = {
    // La sala es parte de la plataforma, no un widget de otra marca.
    SHOW_JITSI_WATERMARK: false,
    SHOW_WATERMARK_FOR_GUESTS: false,
    SHOW_BRAND_WATERMARK: false,
    SHOW_POWERED_BY: false,
    MOBILE_APP_PROMO: false,
    SHOW_CHROME_EXTENSION_BANNER: false,
    HIDE_INVITE_MORE_HEADER: true,
    DISABLE_JOIN_LEAVE_NOTIFICATIONS: true,

    // El iframe es un documento aparte: los tokens CSS de la plataforma no lo
    // alcanzan, así que el color va literal. Es el --background del tema
    // oscuro, y se deja fijo también en tema claro porque una sala de video
    // sobre fondo blanco encandila y le resta contraste a la imagen.
    DEFAULT_BACKGROUND: '#101619',

    TOOLBAR_BUTTONS,
};

declare global {
    interface Window {
        JitsiMeetExternalAPI?: new (domain: string, options: Record<string, unknown>) => JitsiMeetApi;
    }
}

interface JitsiMeetApi {
    dispose: () => void;
    addEventListener: (event: string, listener: (...args: unknown[]) => void) => void;
}

interface JitsiMeetingProps {
    domain: string;
    roomName: string;
    displayName: string;
    onMeetingEnd?: () => void;
}

export function JitsiMeeting({ domain, roomName, displayName, onMeetingEnd }: JitsiMeetingProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const apiRef = useRef<JitsiMeetApi | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let cancelled = false;

        function mount() {
            if (cancelled || !containerRef.current || !window.JitsiMeetExternalAPI) {
                return;
            }

            const api = new window.JitsiMeetExternalAPI(domain, {
                roomName,
                parentNode: containerRef.current,
                width: '100%',
                height: '100%',
                userInfo: { displayName },
                configOverwrite: CONFIG_OVERWRITE,
                interfaceConfigOverwrite: INTERFACE_CONFIG_OVERWRITE,
            });

            api.addEventListener('videoConferenceLeft', () => {
                onMeetingEnd?.();
            });

            apiRef.current = api;
        }

        if (window.JitsiMeetExternalAPI) {
            mount();
        } else {
            const script = document.createElement('script');
            script.src = `https://${domain}/external_api.js`;
            script.async = true;
            script.onload = mount;
            // Sin esto, una red caída deja un recuadro negro sin explicación.
            script.onerror = () => {
                if (!cancelled) {
                    setFailed(true);
                }
            };
            document.body.appendChild(script);
        }

        return () => {
            cancelled = true;
            apiRef.current?.dispose();
            apiRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [domain, roomName, displayName]);

    if (failed) {
        return (
            <div className="bg-muted/40 border-border/70 flex h-[70vh] w-full flex-col items-center justify-center gap-3 rounded-xl border p-6 text-center">
                <span className="bg-warning-soft text-warning flex size-12 items-center justify-center rounded-xl">
                    <WifiOff className="size-5" />
                </span>
                <p className="font-display text-base font-bold">No pudimos abrir la videollamada</p>
                <p className="text-muted-foreground max-w-sm text-sm">
                    La conexión no alcanzó para cargar la sala. Busca un punto con mejor señal y vuelve a intentar; si sigue fallando, comunícate con
                    la IPS.
                </p>
                <button
                    type="button"
                    onClick={() => window.location.reload()}
                    className="text-primary mt-1 text-sm font-semibold underline underline-offset-4"
                >
                    Reintentar
                </button>
            </div>
        );
    }

    return <div ref={containerRef} className="h-[70vh] w-full overflow-hidden rounded-xl border" />;
}
