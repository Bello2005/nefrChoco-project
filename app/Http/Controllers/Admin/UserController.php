<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\UserManagementService;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {}

    public function index(): Response
    {
        $users = User::with('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
            ]);

        return Inertia::render('admin/usuarios/index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/usuarios/create', [
            'roles' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], Role::cases()),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $this->userManagementService->create($request->validated());

        return to_route('admin.usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/usuarios/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
            ],
            'roles' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], Role::cases()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userManagementService->update($user, $request->validated());

        return to_route('admin.usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        $this->userManagementService->delete($user);

        return to_route('admin.usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
