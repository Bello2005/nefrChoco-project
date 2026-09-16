import { Field } from '@/components/forms/field';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck, ShieldOff } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Doble factor', href: '/settings/two-factor' }];

interface Props {
    enabled: boolean;
    pending: boolean;
    qrCode: string | null;
    secret: string | null;
    recoveryCodes: string[] | null;
}

export default function TwoFactor({ enabled, pending, qrCode, secret, recoveryCodes }: Props) {
    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({ password: '' });
    const codesForm = useForm({});

    const confirm: FormEventHandler = (e) => {
        e.preventDefault();
        confirmForm.post(route('two-factor.confirm'), { onSuccess: () => confirmForm.reset('code') });
    };

    const disable: FormEventHandler = (e) => {
        e.preventDefault();
        disableForm.delete(route('two-factor.disable'), { onSuccess: () => disableForm.reset('password') });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Doble factor" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Verificación en dos pasos"
                        description="Agrega un código temporal además de tu contraseña para proteger las historias clínicas"
                    />

                    <div className="flex items-center gap-2">
                        {enabled ? (
                            <Badge variant="success">
                                <ShieldCheck />
                                Activado
                            </Badge>
                        ) : (
                            <Badge variant="outline">
                                <ShieldOff />
                                Desactivado
                            </Badge>
                        )}
                    </div>

                    {recoveryCodes && recoveryCodes.length > 0 && (
                        <div className="border-warning/30 bg-warning-soft space-y-3 rounded-xl border p-4">
                            <div className="flex items-start gap-3">
                                <KeyRound className="text-warning mt-0.5 size-5 shrink-0" aria-hidden="true" />
                                <div>
                                    <p className="text-sm font-semibold">Guarda estos códigos de recuperación</p>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        Son tu única forma de entrar si pierdes el teléfono. Solo se muestran ahora y cada uno sirve una vez.
                                    </p>
                                </div>
                            </div>
                            <ul className="tabular grid grid-cols-2 gap-2 text-sm font-semibold">
                                {recoveryCodes.map((code) => (
                                    <li key={code} className="bg-card rounded-md px-3 py-2 text-center">
                                        {code}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {!enabled && !pending && (
                        <div className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                Necesitas una aplicación de autenticación en tu teléfono (por ejemplo Google Authenticator o Authy). Genera los
                                códigos sin conexión, así que funciona aunque no tengas señal.
                            </p>
                            <Button onClick={() => enableForm.post(route('two-factor.enable'))} disabled={enableForm.processing}>
                                Activar doble factor
                            </Button>
                        </div>
                    )}

                    {pending && (
                        <form onSubmit={confirm} className="space-y-5">
                            <div>
                                <p className="text-sm font-semibold">1. Escanea este código con tu aplicación</p>
                                {qrCode && (
                                    <div
                                        className="bg-card border-border/70 mt-3 inline-block rounded-xl border p-4"
                                        role="img"
                                        aria-label="Código QR para configurar la verificación en dos pasos"
                                        dangerouslySetInnerHTML={{ __html: qrCode }}
                                    />
                                )}
                                {secret && (
                                    <p className="text-muted-foreground mt-3 max-w-sm text-xs">
                                        ¿No puedes escanear? Escribe esta clave en tu aplicación:{' '}
                                        <span className="text-foreground tabular font-semibold break-all">{secret}</span>
                                    </p>
                                )}
                            </div>

                            <div>
                                <p className="mb-2 text-sm font-semibold">2. Escribe el código que aparece</p>
                                <Field htmlFor="code" error={confirmForm.errors.code} className="max-w-xs">
                                    <Input
                                        id="code"
                                        name="code"
                                        required
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        className="tabular text-center text-lg tracking-[0.4em]"
                                        value={confirmForm.data.code}
                                        onChange={(e) => confirmForm.setData('code', e.target.value.replace(/\D/g, ''))}
                                        placeholder="000000"
                                    />
                                </Field>
                            </div>

                            <Button type="submit" disabled={confirmForm.processing}>
                                Confirmar y activar
                            </Button>
                        </form>
                    )}

                    {enabled && (
                        <div className="space-y-8">
                            <div className="space-y-3">
                                <p className="text-sm font-semibold">Códigos de recuperación</p>
                                <p className="text-muted-foreground text-sm">
                                    Si ya usaste varios o crees que alguien más los vio, genera unos nuevos. Los anteriores dejarán de servir.
                                </p>
                                <Button
                                    variant="outline"
                                    onClick={() => codesForm.post(route('two-factor.recovery-codes'))}
                                    disabled={codesForm.processing}
                                >
                                    Generar códigos nuevos
                                </Button>
                            </div>

                            <form onSubmit={disable} className="border-border/70 space-y-4 border-t pt-6">
                                <div>
                                    <p className="text-sm font-semibold">Desactivar</p>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        Confirma tu contraseña para quitar el segundo factor de tu cuenta.
                                    </p>
                                </div>
                                <Field label="Contraseña" htmlFor="password" error={disableForm.errors.password} className="max-w-xs">
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="current-password"
                                        value={disableForm.data.password}
                                        onChange={(e) => disableForm.setData('password', e.target.value)}
                                        placeholder="••••••••"
                                    />
                                </Field>
                                <Button type="submit" variant="destructive" disabled={disableForm.processing}>
                                    Desactivar doble factor
                                </Button>
                            </form>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
