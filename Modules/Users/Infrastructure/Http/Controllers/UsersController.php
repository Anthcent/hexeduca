<?php

namespace Modules\Users\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Users\Infrastructure\Http\Requests\UpdateUserRoleRequest;
use Modules\Users\Infrastructure\Models\User;

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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Users::Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return Inertia::render('Users::Show');
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
    public function update(UpdateUserRoleRequest $request, $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $role = $request->validated('role');

        $this->authorize('assignRole', [$user, $role]);

        $user->syncRoles([$role]);

        return back()->with('success', 'Role updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }
}
