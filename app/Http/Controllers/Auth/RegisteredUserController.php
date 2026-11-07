<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Orcid\OrcidId;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone'       => ['required', 'string', 'max:30'],
            'institution' => ['required', 'string', 'max:255'],
            'country'     => ['required', 'string', 'max:100'],
            'orcid'       => ['nullable', 'string', 'max:19', function (string $attribute, mixed $value, \Closure $fail) {
                if (filled($value) && ! OrcidId::isWellFormed((string) $value)) {
                    $fail('Enter a valid ORCID iD, for example 0000-0002-1825-0097.');
                }
            }],
            'password'    => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $profileFields = ['phone', 'institution', 'country'];

        foreach ($profileFields as $field) {
            $validated[$field] = filled($validated[$field] ?? null)
                ? trim($validated[$field])
                : null;
        }

        $user = User::create([
            'name'        => trim($validated['name']),
            'email'       => strtolower($validated['email']),
            'phone'       => $validated['phone'],
            'institution' => $validated['institution'],
            'country'     => $validated['country'],
            // A self-typed ORCID is stored for display only; it is not
            // verified until the account completes the OAuth flow.
            'orcid'       => filled($validated['orcid'] ?? null) ? OrcidId::normalize($validated['orcid']) : null,
            'role'        => 'Author',
            'password'    => Hash::make($validated['password']),
            'email_verified_at' => config('auth.email_verification_enabled') ? null : now(),
        ]);

        Role::firstOrCreate(['name' => 'Author', 'guard_name' => 'web']);
        $user->syncRoles(['Author']);
        $user->syncLegacyRoleColumn('Author');

        if (config('auth.email_verification_enabled')) {
            event(new Registered($user));
        }
        Auth::login($user);

        return redirect(route('dashboard'));
    }
}
