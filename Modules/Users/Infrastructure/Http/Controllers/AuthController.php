<?php

namespace Modules\Users\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Users\Application\DTOs\UserData;
use Modules\Users\Application\UseCases\RegisterUser;
use Modules\Users\Infrastructure\Http\Requests\LoginRequest;
use Modules\Users\Infrastructure\Http\Requests\RegisterRequest;

/**
 * Login/register/logout for the whole app. See design.md ("Only
 * registration is route-constrained to the landlord host", "Landlord-host
 * login restricted to the super-admin", "Registration does not establish a
 * session on the landlord host") for the reasoning behind the host checks
 * below.
 */
class AuthController extends Controller
{
    /**
     * Same generic message for a wrong password AND for a non-super-admin
     * authenticating on the landlord host — deliberately does not reveal
     * that the account exists but is barred from this host (no user
     * enumeration).
     */
    private const INVALID_CREDENTIALS_MESSAGE = 'These credentials do not match our records.';

    public function __construct(
        private readonly RegisterUser $registerUser,
    ) {}

    public function showLogin(Request $request): Response
    {
        return Inertia::render('Users::Login', [
            'registered' => $request->boolean('registered'),
        ]);
    }

    public function showRegister(): Response
    {
        $schools = School::withoutTenantScope()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Users::Register', [
            'schools' => $schools,
        ]);
    }

    /**
     * `/login` carries no route-level host constraint: it is reachable both
     * on a tenant subdomain (where `Auth::attempt`'s lookup is naturally
     * scoped by TenantScope) and on the landlord host (where no tenant is
     * bound and the lookup is globally unscoped). On the landlord host,
     * only the seeded super-admin may end this request authenticated.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => self::INVALID_CREDENTIALS_MESSAGE,
            ]);
        }

        $request->session()->regenerate();

        if ($this->onLandlordHost($request) && ! $request->user()->hasRole('super-admin')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => self::INVALID_CREDENTIALS_MESSAGE,
            ]);
        }

        return redirect()->intended('/');
    }

    /**
     * Creates the user and assigns the default `student` role, but does NOT
     * authenticate them: `Auth::login()` is never called and the session is
     * never regenerated. The visitor authenticates for the first time
     * through the ordinary `POST /login` flow on their own school's
     * subdomain (see design.md "Registration does not establish a session
     * on the landlord host").
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $school = School::withoutTenantScope()->findOrFail($validated['school_id']);

        $this->registerUser->handle(new UserData(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            schoolId: $school->id,
        ));

        return redirect()->away($school->loginUrl().'?registered=1');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function onLandlordHost(Request $request): bool
    {
        $host = strtolower($request->getHost());
        $landlordHosts = array_map('strtolower', (array) config('tenancy.landlord_hosts', []));

        return in_array($host, $landlordHosts, true);
    }
}
