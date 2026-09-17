import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import { Field } from '@/components/forms/field';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Inicia sesión" description="Ingresa con el correo y la contraseña que te asignó la IPS.">
            <Head title="Iniciar sesión" />

            {status && (
                <div className="border-success/30 bg-success-soft text-success mb-5 rounded-lg border px-3.5 py-2.5 text-sm font-medium">
                    {status}
                </div>
            )}

            <form className="space-y-5" onSubmit={submit}>
                <Field label="Correo electrónico" htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="correo@nefrochoco.co"
                    />
                </Field>

                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <Label htmlFor="password" className="text-sm font-semibold">
                            Contraseña
                        </Label>
                        {canResetPassword && (
                            <TextLink href={route('password.request')} className="text-xs" tabIndex={5}>
                                ¿Olvidaste tu contraseña?
                            </TextLink>
                        )}
                    </div>
                    <Field htmlFor="password" error={errors.password}>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                        />
                    </Field>
                </div>

                <div className="flex items-center gap-3">
                    <Checkbox
                        id="remember"
                        name="remember"
                        tabIndex={3}
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', checked === true)}
                    />
                    <Label htmlFor="remember" className="text-sm font-normal">
                        Mantener la sesión iniciada
                    </Label>
                </div>

                <Button type="submit" size="lg" className="w-full" tabIndex={4} disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Entrar
                </Button>
            </form>

            <p className="text-muted-foreground mt-6 text-center text-sm">
                ¿No tienes cuenta?{' '}
                <TextLink href={route('register')} tabIndex={5}>
                    Regístrate
                </TextLink>
            </p>
        </AuthLayout>
    );
}
