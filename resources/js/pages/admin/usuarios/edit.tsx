import { Field, FormCard } from '@/components/forms/field';
import { PageHeader } from '@/components/page-header';
import { type PractitionerData, PractitionerProfileSection } from '@/components/practitioner-profile-section';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Usuarios', href: '/admin/usuarios' },
    { title: 'Editar', href: '#' },
];

interface UserData {
    id: number;
    name: string;
    email: string;
    role: string | null;
    patientId: number | null;
}

interface LinkablePatient {
    id: number;
    label: string;
}

export default function UsuariosEdit({
    user,
    roles,
    patients,
    practitioner,
}: {
    user: UserData;
    roles: { value: string; label: string }[];
    patients: LinkablePatient[];
    practitioner: PractitionerData | null;
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        role: user.role ?? roles[0]?.value ?? '',
        patient_id: user.patientId ? String(user.patientId) : '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('admin.usuarios.update', user.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${user.name}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                <PageHeader title="Editar usuario" description={user.email} icon={UserRoundCog} />

                <form onSubmit={submit}>
                    <FormCard>
                        <Field label="Nombre" htmlFor="name" error={errors.name}>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                        </Field>

                        <Field label="Correo electrónico" htmlFor="email" error={errors.email}>
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                        </Field>

                        <Field label="Rol" htmlFor="role" error={errors.role}>
                            <NativeSelect id="role" value={data.role} onChange={(e) => setData('role', e.target.value)} required>
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </NativeSelect>
                        </Field>

                        {data.role === 'paciente' && (
                            <Field
                                label="Ficha del paciente"
                                htmlFor="patient_id"
                                error={errors.patient_id}
                                hint={
                                    patients.length > 0
                                        ? 'Vincula la cuenta con su historia clínica. Solo aparecen las fichas que todavía no tienen cuenta.'
                                        : 'No hay fichas sin cuenta. Registra primero al paciente en Pacientes.'
                                }
                            >
                                <NativeSelect id="patient_id" value={data.patient_id} onChange={(e) => setData('patient_id', e.target.value)}>
                                    <option value="">Sin vincular por ahora</option>
                                    {patients.map((patient) => (
                                        <option key={patient.id} value={patient.id}>
                                            {patient.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                        )}

                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Nueva contraseña" htmlFor="password" error={errors.password} hint="Déjala vacía para conservar la actual.">
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoComplete="new-password"
                                />
                            </Field>

                            <Field label="Confirmar contraseña" htmlFor="password_confirmation">
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    autoComplete="new-password"
                                />
                            </Field>
                        </div>

                        <div className="flex items-center gap-3 pt-1">
                            <Button disabled={processing}>Guardar cambios</Button>
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('admin.usuarios.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </FormCard>
                </form>

                {practitioner && <PractitionerProfileSection userId={user.id} practitioner={practitioner} />}
            </div>
        </AppLayout>
    );
}
