import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashToast } from '@/components/flash-toast';
import { OfflineSyncProvider } from '@/hooks/use-offline-sync';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    const { url } = usePage();

    return (
        <OfflineSyncProvider>
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent variant="sidebar">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {/* La clave por URL reinicia la animación de entrada en cada navegación */}
                    <div key={url} className="animate-in-up mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:px-6 lg:py-8">
                        {children}
                    </div>
                    <FlashToast />
                </AppContent>
            </AppShell>
        </OfflineSyncProvider>
    );
}
