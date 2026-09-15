import AppLogoIcon from '@/components/app-logo-icon';
import { Link } from '@inertiajs/react';
import { Activity, ShieldCheck, Wifi } from 'lucide-react';

interface AuthLayoutProps {
    children: React.ReactNode;
    name?: string;
    title?: string;
    description?: string;
}

const highlights = [
    { icon: Activity, text: 'Seguimiento de enfermedades crónicas no transmisibles' },
    { icon: Wifi, text: 'Diseñada para funcionar con conectividad limitada' },
    { icon: ShieldCheck, text: 'Datos protegidos según la Ley 1581 de 2012' },
];

export default function AuthSimpleLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            {/* Panel de marca: solo en pantallas grandes para no penalizar la carga en móvil rural */}
            <aside className="bg-primary text-primary-foreground relative hidden flex-col justify-between overflow-hidden p-10 lg:flex">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full opacity-20"
                    style={{ background: 'radial-gradient(circle, white, transparent 70%)' }}
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -bottom-32 -left-20 size-96 rounded-full opacity-15"
                    style={{ background: 'radial-gradient(circle, white, transparent 70%)' }}
                />

                <Link href={route('home')} className="relative flex items-center gap-3">
                    <span className="bg-primary-foreground/15 flex size-11 items-center justify-center rounded-xl backdrop-blur-sm">
                        <AppLogoIcon className="size-6" />
                    </span>
                    <span>
                        <span className="font-display block text-base leading-tight font-extrabold">IPS NefroChocó</span>
                        <span className="text-primary-foreground/70 block text-xs">Plataforma de telemedicina</span>
                    </span>
                </Link>

                <div className="relative space-y-8">
                    <p className="font-display max-w-md text-3xl leading-tight font-extrabold tracking-tight">
                        Atención especializada que llega hasta el último corregimiento del Chocó.
                    </p>

                    <ul className="space-y-4">
                        {highlights.map((highlight) => (
                            <li key={highlight.text} className="flex items-center gap-3">
                                <span className="bg-primary-foreground/15 flex size-9 shrink-0 items-center justify-center rounded-lg">
                                    <highlight.icon className="size-4" />
                                </span>
                                <span className="text-primary-foreground/85 text-sm">{highlight.text}</span>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="text-primary-foreground/60 relative text-xs">
                    Promoción, prevención y seguimiento de ECNT para el departamento del Chocó.
                </p>
            </aside>

            <main className="flex items-center justify-center p-6 sm:p-10">
                <div className="w-full max-w-sm">
                    <Link href={route('home')} className="mb-8 flex items-center justify-center gap-2.5 lg:hidden">
                        <span className="bg-primary text-primary-foreground flex size-10 items-center justify-center rounded-xl">
                            <AppLogoIcon className="size-5" />
                        </span>
                        <span className="font-display text-base font-extrabold">IPS NefroChocó</span>
                    </Link>

                    <div className="mb-7 space-y-1.5">
                        <h1 className="text-2xl font-extrabold">{title}</h1>
                        {description && <p className="text-muted-foreground text-sm">{description}</p>}
                    </div>

                    {children}
                </div>
            </main>
        </div>
    );
}
