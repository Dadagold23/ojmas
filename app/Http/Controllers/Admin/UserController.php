<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private array $managedRoles = ['Editor', 'Reviewer', 'Author', 'Managing Editor'];

    public function index(Request $request)
    {
        $query = User::with('roles')->orderBy('name');

        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $request->role));
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        $users = $query->paginate(15)->withQueryString();
        $roles = Role::whereIn('name', $this->managedRoles)->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create()
    {
        $roles = Role::whereIn('name', $this->managedRoles)->orderBy('name')->get();
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'institution' => ['nullable', 'string', 'max:255'],
            'country'     => ['nullable', 'string', 'max:100'],
            'role'        => ['required', 'string', 'in:'.implode(',', $this->managedRoles)],
            'password'    => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'phone'             => $validated['phone'] ?? null,
            'institution'       => $validated['institution'] ?? null,
            'country'           => $validated['country'] ?? null,
            'role'              => $validated['role'],
            'password'          => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$validated['role']]);
        $user->syncLegacyRoleColumn($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', $validated['role'].' account created successfully.');
    }

    public function show(User $user)
    {
        $user->load('roles');
        $submissions = $user->submissions()->latest()->take(5)->get();
        $assignments = $user->reviewerAssignments()->with('submission')->latest()->take(5)->get();

        return view('admin.users.show', compact('user', 'submissions', 'assignments'));
    }

    public function edit(User $user)
    {
        $user->load('roles');
        $roles = Role::whereIn('name', $this->managedRoles)->orderBy('name')->get();
        $currentRole = $this->resolveManagedRole($user);

        return view('admin.users.edit', compact('user', 'roles', 'currentRole'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone'       => ['nullable', 'string', 'max:30'],
            'institution' => ['nullable', 'string', 'max:255'],
            'country'     => ['nullable', 'string', 'max:100'],
            'role'        => ['required', 'string', 'in:'.implode(',', $this->managedRoles)],
            'password'    => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user->update([
            'name'        => $validated['name'],
            'email'       => $validated['email'],
            'phone'       => $validated['phone'] ?? null,
            'institution' => $validated['institution'] ?? null,
            'country'     => $validated['country'] ?? null,
            'role'        => $validated['role'],
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);
        $user->syncLegacyRoleColumn($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        abort_if($user->hasRole('Super Admin'), 403, 'Cannot delete a Super Admin.');
        abort_if($user->id === auth()->id(), 403, 'Cannot delete your own account.');

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    private function resolveManagedRole(User $user): ?string
    {
        foreach (['Managing Editor', 'Editor', 'Reviewer', 'Author'] as $roleName) {
            if ($user->hasRole($roleName)) {
                return $roleName;
            }
        }

        return $user->roles->first()?->name;
    }
}
