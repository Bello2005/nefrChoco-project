import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import { Field } from '@/components/forms/field';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Crea tu cuenta" description="Regístrate para acceder a tu información de salud.">
            <Head title="Registro" />

            <form className="space-y-5" onSubmit={submit}>
                <Field label="Nombre completo" htmlFor="name" error={errors.name}>
                    <Input
                        id="name"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Nombre y apellidos"
                    />
                </Field>

                <Field label="Correo electrónico" htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        required
                        tabIndex={2}
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="correo@ejemplo.com"
                    />
                </Field>

                <Field label="Contraseña" htmlFor="password" error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabIndex={3}
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                    />
                </Field>

                <Field label="Confirmar contraseña" htmlFor="password_confirmation" error={errors.password_confirmation}>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        tabIndex={4}
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        placeholder="••••••••"
                    />
                </Field>

                <Button type="submit" size="lg" className="w-full" tabIndex={5} disabled={processing}>
                    {processing && <LoaderCircle className="animate-spin" />}
                    Crear cuenta
                </Button>
            </form>

            <p className="text-muted-foreground mt-6 text-center text-sm">
                ¿Ya tienes cuenta?{' '}
                <TextLink href={route('login')} tabIndex={6}>
                    Inicia sesión
                </TextLink>
            </p>
        </AuthLayout>
    );
}
