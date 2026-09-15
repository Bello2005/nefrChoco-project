import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatRelative } from '@/lib/format';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Bell, CalendarClock, CheckCheck, TriangleAlert } from 'lucide-react';

const iconByType: Record<string, typeof Bell> = {
    signo_vital_fuera_de_rango: TriangleAlert,
    cita_agendada: CalendarClock,
};

export function NotificationBell() {
    const { notifications } = usePage<SharedData>().props;
    const items = notifications ?? [];

    const markRead = (id: string, url: string | null) => {
        router.post(
            route('notificaciones.read', id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (url) router.visit(url);
                },
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative size-9" aria-label={`Notificaciones (${items.length} sin leer)`}>
                    <Bell className="size-5" />
                    {items.length > 0 && (
                        <span className="bg-brand text-brand-foreground tabular absolute top-1 right-1 flex size-4 items-center justify-center rounded-full text-[10px] font-bold">
                            {items.length > 9 ? '9+' : items.length}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between border-b px-3 py-2.5">
                    <p className="text-sm font-bold">Notificaciones</p>
                    {items.length > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-xs"
                            onClick={() => router.post(route('notificaciones.read-all'), {}, { preserveScroll: true })}
                        >
                            <CheckCheck />
                            Marcar leídas
                        </Button>
                    )}
                </div>

                {items.length === 0 ? (
                    <p className="text-muted-foreground px-3 py-8 text-center text-sm">No tienes notificaciones pendientes.</p>
                ) : (
                    <ul className="max-h-80 overflow-y-auto">
                        {items.map((notification) => {
                            const Icon = iconByType[notification.type ?? ''] ?? Bell;

                            return (
                                <li key={notification.id} className="border-b last:border-0">
                                    <button
                                        type="button"
                                        onClick={() => markRead(notification.id, notification.url)}
                                        className="hover:bg-muted/60 flex w-full gap-2.5 px-3 py-2.5 text-left transition-colors"
                                    >
                                        <span
                                            className={`mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg ${
                                                notification.type === 'signo_vital_fuera_de_rango'
                                                    ? 'bg-warning-soft text-warning'
                                                    : 'bg-primary-soft text-accent-foreground'
                                            }`}
                                        >
                                            <Icon className="size-3.5" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-semibold">{notification.title}</span>
                                            <span className="text-muted-foreground block text-xs">{notification.message}</span>
                                            <span className="text-muted-foreground/80 mt-0.5 block text-[11px]">
                                                {formatRelative(notification.createdAt)}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
