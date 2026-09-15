import { WifiOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

// TODO: apuntar a Jitsi autoalojado en el VPS cuando esté listo
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
                configOverwrite: { prejoinPageEnabled: false },
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
