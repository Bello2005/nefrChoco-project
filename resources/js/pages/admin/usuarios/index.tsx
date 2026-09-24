import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { initialsFrom } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { UserPlus, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel administrativo', href: '/admin' },
    { title: 'Usuarios', href: '/admin/usuarios' },
];

const roleConfig: Record<string, { label: string; variant: 'default' | 'info' | 'success' }> = {
    admin: { label: 'Administrador', variant: 'default' },
    medico: { label: 'Médico', variant: 'info' },
    paciente: { label: 'Paciente', variant: 'success' },
};

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string | null;
    deactivated: boolean;
    isSelf: boolean;
}

export default function UsuariosIndex({ users }: { users: UserRow[] }) {
    // Las cuentas no se borran: borrar a un médico arrastraba sus citas y sus
    // notas. Desactivar solo le quita el acceso.
    // TODO doc: guía de usuario — Desactivar/Reactivar en Admin → Usuarios; ya no hay "Eliminar cuenta" en Ajustes.
    const handleDeactivate = (user: UserRow) => {
        if (confirm(`¿Desactivar a ${user.name}? No podrá entrar, pero todo lo que registró se conserva.`)) {
            router.patch(route('admin.usuarios.desactivar', user.id));
        }
    };

    const handleReactivate = (user: UserRow) => {
        if (confirm(`¿Reactivar a ${user.name}? Podrá volver a entrar con su contraseña.`)) {
            router.patch(route('admin.usuarios.reactivar', user.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuarios" />

            <div className="space-y-6">
                <PageHeader
                    title="Usuarios"
                    description="Cuentas con acceso a la plataforma y el rol que determina lo que puede ver cada una."
                    icon={Users}
                    actions={
                        <Button asChild>
                            <Link href={route('admin.usuarios.create')}>
                                <UserPlus />
                                Nuevo usuario
                            </Link>
                        </Button>
                    }
                />

                {users.length === 0 ? (
                    <EmptyState icon={Users} title="No hay usuarios registrados" />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Correo</TableHead>
                                <TableHead>Rol</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((user) => {
                                const config = user.role ? roleConfig[user.role] : null;

                                return (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <span className="flex items-center gap-3">
                                                <span className="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                                    {initialsFrom(user.name)}
                                                </span>
                                                <span className="font-semibold">{user.name}</span>
                                                {user.deactivated && <Badge variant="outline">Desactivada</Badge>}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">{user.email}</TableCell>
                                        <TableCell>
                                            {config ? (
                                                <Badge variant={config.variant}>{config.label}</Badge>
                                            ) : (
                                                <span className="text-muted-foreground text-sm">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={route('admin.usuarios.edit', user.id)}>Editar</Link>
                                                </Button>
                                                {user.deactivated ? (
                                                    <Button variant="ghost" size="sm" onClick={() => handleReactivate(user)}>
                                                        Reactivar
                                                    </Button>
                                                ) : (
                                                    !user.isSelf && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-destructive hover:bg-destructive-soft"
                                                            onClick={() => handleDeactivate(user)}
                                                        >
                                                            Desactivar
                                                        </Button>
                                                    )
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
            </div>
        </AppLayout>
    );
}
