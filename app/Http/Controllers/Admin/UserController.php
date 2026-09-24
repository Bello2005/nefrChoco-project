<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Patient;
use App\Models\User;
use App\Services\Catalogs\CodeCatalog;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $users = User::with(['roles', 'practitionerProfile'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                // Aviso sin bloquear: al médico le falta el perfil o la verificación RETHUS.
                'practitionerPending' => $user->hasRole(Role::Medico->value)
                    && (! $user->practitionerProfile?->isComplete() || $user->practitionerProfile?->rethus_verified_at === null),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'deactivated' => $user->isDeactivated(),
                // Sin botón para uno mismo: un admin que se desactiva por error
                // podría dejar a la IPS sin nadie que administre.
                'isSelf' => $user->is($request->user()),
            ]);

        return Inertia::render('admin/usuarios/index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/usuarios/create', [
            'roles' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], Role::cases()),
            'patients' => $this->linkablePatients(),
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
                'patientId' => $user->patient?->id,
            ],
            // Solo se muestra para el rol medico (RDA: profesional activo en RETHUS).
            'practitioner' => $user->hasRole(Role::Medico->value) ? $this->practitionerData($user) : null,
            'roles' => array_map(fn (Role $role) => ['value' => $role->value, 'label' => $role->label()], Role::cases()),
            'patients' => $this->linkablePatients($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->userManagementService->update($user, $request->validated());

        return to_route('admin.usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        try {
            $this->userManagementService->deactivate($user, $request->user());
        } catch (\DomainException $exception) {
            return to_route('admin.usuarios.index')->with('error', $exception->getMessage());
        }

        return to_route('admin.usuarios.index')->with('success', "{$user->name} quedó desactivada. Lo que registró se conserva.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        $this->userManagementService->reactivate($user);

        return to_route('admin.usuarios.index')->with('success', "{$user->name} puede volver a entrar.");
    }

    /** @return array<string, mixed> */
    private function practitionerData(User $user): array
    {
        $profile = $user->practitionerProfile;

        return [
            'documentType' => $profile?->document_type,
            'documentNumber' => $profile?->document_number,
            'profession' => $profile?->profession,
            'professionalRegistration' => $profile?->professional_registration,
            'specialty' => $profile?->specialty,
            'missing' => $profile?->missingFields() ?? ['datos profesionales'],
            'documentTypeCatalog' => app(CodeCatalog::class)->has('tipo_documento') ? 'tipo_documento' : null,
            'documentTypeLabel' => app(CodeCatalog::class)->display('tipo_documento', $profile?->document_type),
            'rethusVerifiedAt' => $profile?->rethus_verified_at,
            'rethusVerifiedBy' => $profile?->verifier?->name,
            'rethusNote' => $profile?->rethus_note,
        ];
    }

    /**
     * Fichas que se pueden vincular a una cuenta: las que no tienen ninguna, más
     * la del usuario que se edita, que si no desaparecería de su propio selector.
     *
     * @return array<int, array{id: int, label: string}>
     */
    private function linkablePatients(?User $user = null): array
    {
        return Patient::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('user_id');

                if ($user !== null) {
                    $query->orWhere('user_id', $user->id);
                }
            })
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'document_type', 'document_number'])
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'label' => $patient->full_name.' · '.$patient->document_type.' '.$patient->document_number,
            ])
            ->all();
    }
}
