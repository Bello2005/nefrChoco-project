import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { listPending } from '@/lib/offline-queue';
import { clearPrivateCache } from '@/lib/register-service-worker';
import { type User } from '@/types';
import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();

    // Se avisa antes de salir, pero nunca se bloquea el cierre de sesión: lo
    // pendiente queda guardado en el teléfono y espera a que su dueño vuelva.
    const handleLogout = async () => {
        clearPrivateCache();
        cleanup();

        const pending = await listPending(user.id);
        if (pending.length > 0) {
            const medicion = pending.length === 1 ? 'medición' : 'mediciones';
            window.alert(
                `Tienes ${pending.length} ${medicion} sin enviar. Se quedan guardadas en este teléfono y se enviarán cuando vuelvas a entrar con señal.`,
            );
        }

        router.post(route('logout'));
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link className="block w-full" href={route('profile.edit')} as="button" prefetch onClick={cleanup}>
                        <Settings className="mr-2" />
                        Mi cuenta
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={() => void handleLogout()}>
                <LogOut className="mr-2" />
                Cerrar sesión
            </DropdownMenuItem>
        </>
    );
}
