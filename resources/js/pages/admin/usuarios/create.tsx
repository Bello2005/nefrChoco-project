import { Field, FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Usuarios', href: '/admin/usuarios' },
    { title: 'Nuevo', href: '/admin/usuarios/crear' },
];

export default function UsuariosCreate({ roles }: { roles: { value: string; label: string }[] }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: roles[0]?.value ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.usuarios.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo usuario" />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Nuevo usuario" description="Crea una cuenta y asígnale el rol que corresponda." icon={UserPlus} />

                <form onSubmit={submit}>
                    <FormCard>
                        <Field label="Nombre" htmlFor="name" error={errors.name}>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        </Field>

                        <Field label="Correo electrónico" htmlFor="email" error={errors.email}>
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        </Field>

                        <Field label="Rol" htmlFor="role" error={errors.role} hint="Define a qué módulos tendrá acceso la cuenta.">
                            <NativeSelect id="role" value={data.role} onChange={(e) => setData('role', e.target.value)} required>
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Contraseña" htmlFor="password" error={errors.password}>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required
                                    autoComplete="new-password"
                                />
                            </Field>

                            <Field label="Confirmar contraseña" htmlFor="password_confirmation">
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    required
                                    autoComplete="new-password"
                                />
                            </Field>
                        </div>

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing}>Crear usuario</Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('admin.usuarios.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </FormCard>
                </form>
            </div>
        </AppLayout>
    );
}
