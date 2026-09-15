import { useEffect, useRef } from 'react';

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
            document.body.appendChild(script);
        }

        return () => {
            cancelled = true;
            apiRef.current?.dispose();
            apiRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [domain, roomName, displayName]);

    return <div ref={containerRef} className="h-[70vh] w-full overflow-hidden rounded-xl border" />;
}
