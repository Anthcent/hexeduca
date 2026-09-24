<?php

namespace Modules\Users\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use App\Tenancy\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Users\Application\DTOs\UserData;
use Modules\Users\Application\UseCases\DeleteUser;
use Modules\Users\Application\UseCases\RegisterUser;
use Modules\Users\Domain\Exceptions\UserCannotBeDeleted;
use Modules\Users\Infrastructure\Http\Requests\StoreUserRequest;
use Modules\Users\Infrastructure\Http\Requests\UpdateUserRoleRequest;
use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Public\Events\UserUpdated;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * `User::all()` is scoped by `TenantScope` automatically: a tenant
     * staff/admin sees only their own school's users, while the landlord
     * (no tenant bound on the landlord host) sees every user across every
     * school — matching super-admin's cross-tenant role-assignment reach.
     */
    public function index(): Response
    {
        return Inertia::render('Users::Index', [
            'users' => User::orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ]),
        ]);
    }

    /**
     * Show the form for creating a new user.
     *
     * On a tenant subdomain the new user always joins the bound tenant; only
     * a super-admin on the landlord host (no tenant bound) picks a school.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        $tenant = current_tenant();

        return Inertia::render('Users::Create', [
            'roles' => StoreUserRequest::ASSIGNABLE_ROLES,
            'school' => $tenant?->only(['id', 'name']),
            'schools' => $tenant === null
                ? School::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    /**
     * Store a newly created user through the `RegisterUser` use case.
     */
    public function store(StoreUserRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle(new UserData(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            schoolId: $request->schoolId(),
            role: $request->validated('role'),
        ));

        return redirect()->route('users.show', $user->id())->with('success', 'Usuario creado.');
    }

    /**
     * Show the specified user. A user from another school is a 404.
     */
    public function show($id): Response
    {
        $user = $this->findVisibleUser($id);
        $role = $user->getRoleNames()->first();

        return Inertia::render('Users::Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'school' => $user->school_id === null ? null : School::query()->find($user->school_id)?->name,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'can' => [
                'edit' => Gate::allows('assignRole', [$user, $role ?? 'student']),
                'delete' => Gate::allows('delete', $user),
            ],
        ]);
    }

    /**
     * The 4 fixed seeded Spatie roles a single-select role dropdown is
     * limited to. See `RoleAndPermissionSeeder`.
     *
     * @var list<string>
     */
    private const ROLES = ['student', 'teacher', 'staff/admin', 'super-admin'];

    /**
     * Show the form for editing the specified resource.
     *
     * `User::findOrFail($id)` is scoped by `TenantScope` to the acting
     * admin's own tenant only when a tenant is bound to the request (i.e.
     * on a real tenant subdomain). `UserPolicy::assignRole`'s explicit
     * `school_id` comparison is the enforcement that always applies,
     * regardless of host — see design.md.
     */
    public function edit($id): Response
    {
        $user = User::findOrFail($id);

        $this->authorize('assignRole', [$user, $user->getRoleNames()->first()]);

        return Inertia::render('Users::Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ],
            'roles' => self::ROLES,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateUserRoleRequest $request,
        $id,
        OutboxEventRecorder $outbox,
    ): RedirectResponse {
        $role = $request->validated('role');

        DB::transaction(function () use ($id, $role, $outbox): void {
            $user = User::query()->lockForUpdate()->findOrFail($id);

            $this->authorize('assignRole', [$user, $role]);

            $user->syncRoles([$role]);

            $latestPayload = DB::table('integration_outbox_events')
                ->where('aggregate_type', 'User')
                ->where('aggregate_id', (string) $user->id)
                ->whereIn('event_name', ['user.created', 'user.updated'])
                ->orderByDesc('id')
                ->value('payload');
            $latestVersion = $latestPayload === null
                ? 1
                : (int) (json_decode((string) $latestPayload, true)['version'] ?? 1);

            $outbox->record(new UserUpdated(
                userId: $user->id,
                name: $user->name,
                email: $user->email,
                schoolId: $user->school_id,
                roles: $user->getRoleNames()->values()->all(),
                version: $latestVersion + 1,
            ));
        });

        return back()->with('success', 'Role updated.');
    }

    /**
     * Remove the specified user. Tenant ownership is a 404 (same as show);
     * deleting yourself or a super-admin is a 403 (`UserPolicy::delete`).
     */
    public function destroy($id, DeleteUser $deleteUser): RedirectResponse
    {
        $user = $this->findVisibleUser($id);

        $this->authorize('delete', $user);

        try {
            $deleteUser->handle($user->id);
        } catch (UserCannotBeDeleted) {
            return back()->with('error', 'No se puede eliminar: el usuario tiene matrículas u ofertas académicas asociadas.');
        }

        return redirect()->route('users.index')->with('success', 'Usuario eliminado.');
    }

    /**
     * `TenantScope` already hides other schools' users on a tenant host;
     * `UserPolicy::view` is the host-independent check, reported as 404 so
     * a foreign user is indistinguishable from a missing one.
     */
    private function findVisibleUser(mixed $id): User
    {
        $user = User::findOrFail($id);

        abort_unless(Gate::allows('view', $user), 404);

        return $user;
    }
}
