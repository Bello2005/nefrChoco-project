import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, TriangleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export function FlashToast() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState(false);
    const message = flash?.success ?? flash?.error;
    const isError = Boolean(flash?.error);

    useEffect(() => {
        if (!message) {
            setVisible(false);
            return;
        }

        setVisible(true);
        const timer = setTimeout(() => setVisible(false), 4500);

        return () => clearTimeout(timer);
    }, [message]);

    if (!message || !visible) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4 sm:bottom-8">
            <div
                role="status"
                className="animate-in-up bg-popover border-border pointer-events-auto flex max-w-md items-start gap-3 rounded-xl border px-4 py-3 shadow-lg"
            >
                <span className={isError ? 'text-destructive mt-0.5' : 'text-success mt-0.5'}>
                    {isError ? <TriangleAlert className="size-5" /> : <CheckCircle2 className="size-5" />}
                </span>
                <p className="text-sm font-medium">{message}</p>
                <button
                    type="button"
                    onClick={() => setVisible(false)}
                    className="text-muted-foreground hover:text-foreground mt-0.5 -mr-1 transition-colors"
                    aria-label="Cerrar notificación"
                >
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}
