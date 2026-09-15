import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, BookOpen, CalendarCheck, ShieldCheck, Video, Wifi } from 'lucide-react';

const features = [
    {
        icon: Video,
        title: 'Teleconsulta segura',
        description: 'Videollamadas con salas privadas y únicas por cita, sin que el paciente deba desplazarse durante horas.',
    },
    {
        icon: Activity,
        title: 'Telemonitoreo de signos vitales',
        description: 'El paciente reporta sus mediciones y el equipo clínico recibe alertas cuando algo se sale del rango.',
    },
    {
        icon: CalendarCheck,
        title: 'Agenda y seguimiento',
        description: 'Citas, historia clínica y formularios de tamizaje en un solo lugar, con trazabilidad completa.',
    },
    {
        icon: BookOpen,
        title: 'Educación en salud',
        description: 'Material de promoción y prevención en lenguaje claro, adaptado al contexto del territorio.',
    },
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Telemedicina para el Chocó" />

            <div className="bg-background min-h-svh">
                <header className="border-border/60 border-b">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-4">
                        <div className="flex items-center gap-2.5">
                            <span className="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-xl">
                                <AppLogoIcon className="size-5" />
                            </span>
                            <span>
                                <span className="font-display block text-sm leading-tight font-extrabold">IPS NefroChocó</span>
                                <span className="text-muted-foreground block text-[11px] leading-tight">Telemedicina ECNT</span>
                            </span>
                        </div>

                        <nav className="flex items-center gap-2">
                            {auth?.user ? (
                                <Button asChild>
                                    <Link href={route('dashboard')}>Ir a la plataforma</Link>
                                </Button>
                            ) : (
                                <>
                                    <Button variant="ghost" asChild>
                                        <Link href={route('login')}>Iniciar sesión</Link>
                                    </Button>
                                    <Button asChild>
                                        <Link href={route('register')}>Crear cuenta</Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="bg-grid-soft relative overflow-hidden">
                        <div className="mx-auto max-w-6xl px-6 py-20 lg:py-28">
                            <div className="max-w-2xl">
                                <span className="bg-primary-soft text-accent-foreground inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-semibold">
                                    <Wifi className="size-3.5" />
                                    Pensada para conectividad rural intermitente
                                </span>

                                <h1 className="font-display mt-6 text-4xl leading-[1.08] font-extrabold tracking-tight sm:text-5xl lg:text-6xl">
                                    Atención crónica que llega hasta donde vive la gente
                                </h1>

                                <p className="text-muted-foreground mt-6 max-w-xl text-lg leading-relaxed">
                                    Plataforma de telemedicina de la IPS NefroChocó para la promoción, prevención y seguimiento de enfermedades
                                    crónicas no transmisibles en el departamento del Chocó.
                                </p>

                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Button size="lg" asChild>
                                        <Link href={auth?.user ? route('dashboard') : route('login')}>
                                            {auth?.user ? 'Ir a la plataforma' : 'Ingresar a la plataforma'}
                                        </Link>
                                    </Button>
                                    <Button size="lg" variant="outline" asChild>
                                        <a href="#modulos">Conocer los módulos</a>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="modulos" className="border-border/60 border-t">
                        <div className="mx-auto max-w-6xl px-6 py-16 lg:py-20">
                            <h2 className="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Qué resuelve la plataforma</h2>
                            <p className="text-muted-foreground mt-2 max-w-2xl">
                                Cuatro capacidades pensadas para la dispersión poblacional y las distancias del territorio chocoano.
                            </p>

                            <div className="mt-10 grid gap-5 sm:grid-cols-2">
                                {features.map((feature) => (
                                    <article key={feature.title} className="bg-card border-border/70 rounded-xl border p-6 shadow-sm">
                                        <span className="bg-primary-soft text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                            <feature.icon className="size-5" />
                                        </span>
                                        <h3 className="font-display mt-4 text-base font-bold">{feature.title}</h3>
                                        <p className="text-muted-foreground mt-2 text-sm leading-relaxed">{feature.description}</p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="border-border/60 border-t">
                        <div className="mx-auto max-w-6xl px-6 py-16">
                            <div className="bg-primary text-primary-foreground relative overflow-hidden rounded-2xl p-10 lg:p-14">
                                <div
                                    aria-hidden
                                    className="pointer-events-none absolute -top-20 -right-20 size-80 rounded-full opacity-20"
                                    style={{ background: 'radial-gradient(circle, white, transparent 70%)' }}
                                />
                                <ShieldCheck className="relative size-9" />
                                <h2 className="font-display relative mt-5 max-w-2xl text-2xl leading-snug font-extrabold tracking-tight sm:text-3xl">
                                    Datos clínicos tratados con la seriedad que exige la ley
                                </h2>
                                <p className="text-primary-foreground/80 relative mt-4 max-w-2xl leading-relaxed">
                                    Control de acceso por rol, registro de auditoría de cada consulta a una historia clínica y trazabilidad completa
                                    de los cambios, conforme a la Ley 1581 de 2012 de protección de datos personales.
                                </p>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="border-border/60 border-t">
                    <div className="text-muted-foreground mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-8 text-sm">
                        <p>IPS NefroChocó · Quibdó, Chocó, Colombia</p>
                        <p>Promoción, prevención y seguimiento de ECNT</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
