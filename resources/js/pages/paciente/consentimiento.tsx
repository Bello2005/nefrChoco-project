import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Eye, FileLock2, Share2, ShieldCheck, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Autorización de datos', href: '/paciente/consentimiento' }];

const rights = [
    { icon: Eye, text: 'Conocer, actualizar y rectificar tus datos en cualquier momento.' },
    { icon: FileLock2, text: 'Solicitar prueba de la autorización que otorgas ahora.' },
    { icon: Share2, text: 'Ser informado sobre el uso que se le da a tu información.' },
    { icon: Trash2, text: 'Revocar la autorización y solicitar la supresión de tus datos.' },
];

interface Props {
    version: string;
    contactEmail: string;
    hasProfile: boolean;
}

export default function Consentimiento({ version, contactEmail, hasProfile }: Props) {
    const { data, setData, post, processing, errors } = useForm({ accepted: false as boolean });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('paciente.consentimiento.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Autorización de datos" />

            <div className="mx-auto max-w-2xl space-y-6">
                <div className="flex items-start gap-3.5">
                    <span className="bg-primary-soft text-accent-foreground flex size-11 shrink-0 items-center justify-center rounded-xl">
                        <ShieldCheck className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-2xl font-extrabold">Autorización para el tratamiento de tus datos</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Antes de continuar necesitamos tu autorización, como lo exige la Ley 1581 de 2012.
                        </p>
                    </div>
                </div>

                <div className="bg-card border-border/70 space-y-5 rounded-xl border p-6 shadow-sm">
                    <section className="space-y-2">
                        <h2 className="font-display text-sm font-bold">Para qué usamos tu información</h2>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            La IPS NefroChocó tratará tus datos personales y de salud únicamente para prestarte atención médica dentro del programa de
                            enfermedades crónicas no transmisibles: agendar tus citas, llevar tu historia clínica, hacer seguimiento a tus signos
                            vitales y enviarte material educativo.
                        </p>
                    </section>

                    <section className="space-y-2">
                        <h2 className="font-display text-sm font-bold">Quién puede verla</h2>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            Solo el personal autorizado de la IPS que participa en tu atención. Tu información se guarda cifrada y cada consulta a tu
                            historia clínica queda registrada con el nombre de quien la abrió, la fecha y la hora. No compartimos tus datos con
                            terceros ni se usan con fines comerciales.
                        </p>
                    </section>

                    <section className="space-y-3">
                        <h2 className="font-display text-sm font-bold">Tus derechos como titular</h2>
                        <ul className="space-y-2.5">
                            {rights.map((right) => (
                                <li key={right.text} className="flex items-start gap-2.5 text-sm">
                                    <span className="bg-muted text-muted-foreground mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md">
                                        <right.icon className="size-3.5" />
                                    </span>
                                    <span className="text-muted-foreground">{right.text}</span>
                                </li>
                            ))}
                        </ul>
                        <p className="text-muted-foreground text-xs">
                            Puedes ejercerlos escribiendo a <span className="text-foreground font-medium">{contactEmail}</span>.
                        </p>
                    </section>
                </div>

                {!hasProfile ? (
                    <div className="border-warning/30 bg-warning-soft rounded-xl border p-4 text-sm">
                        Tu cuenta todavía no está vinculada a una ficha de paciente, así que aún no hay datos clínicos que autorizar. Comunícate con
                        la IPS para completar tu vinculación.
                    </div>
                ) : (
                    <form onSubmit={submit} className="bg-card border-border/70 space-y-4 rounded-xl border p-6 shadow-sm">
                        <label className="flex cursor-pointer items-start gap-3">
                            <Checkbox
                                id="accepted"
                                checked={data.accepted}
                                onCheckedChange={(checked) => setData('accepted', checked === true)}
                                className="mt-0.5"
                            />
                            <span className="text-sm leading-relaxed">
                                Autorizo de manera libre, previa, expresa e informada a la IPS NefroChocó para tratar mis datos personales y de salud
                                con las finalidades descritas arriba.
                            </span>
                        </label>

                        <InputError message={errors.accepted} />

                        <div className="flex flex-wrap items-center gap-3">
                            <Button disabled={processing || !data.accepted}>Autorizar y continuar</Button>
                            <span className="text-muted-foreground text-xs">Versión {version}</span>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
