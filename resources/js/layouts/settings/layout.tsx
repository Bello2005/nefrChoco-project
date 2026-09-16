import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Perfil',
        url: '/settings/profile',
        icon: null,
    },
    {
        title: 'Contraseña',
        url: '/settings/password',
        icon: null,
    },
    {
        title: 'Doble factor',
        url: '/settings/two-factor',
        icon: null,
    },
    {
        title: 'Apariencia',
        url: '/settings/appearance',
        icon: null,
    },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    // La ruta se lee de Inertia y no de window.location: este componente
    // también se renderiza en el servidor, donde window no existe.
    const currentPath = usePage().url.split('?')[0];

    return (
        <div>
            <Heading title="Mi cuenta" description="Administra tu perfil y la configuración de tu cuenta" />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {sidebarNavItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': currentPath === item.url,
                                })}
                            >
                                <Link href={item.url} prefetch>
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
