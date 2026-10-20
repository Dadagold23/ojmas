<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;
    use HasRoles {
        hasRole as traitHasRole;
        scopeRole as traitScopeRole;
    }

    protected $fillable = [
        'name', 'email', 'phone', 'institution', 'country', 'password'
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'author_id');
    }
    public function reviewerAssignments()
    {
        return $this->hasMany(ReviewerAssignment::class, 'reviewer_id');
    }

    public function editorialDecisions()
    {
        return $this->hasMany(EditorialDecision::class, 'editor_id');
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function syncLegacyRoleColumn(?string $roleName): void
    {
        $this->forceFill([
            'role' => $roleName,
        ])->saveQuietly();
    }

    public function hasRole($roles, ?string $guard = null): bool
    {
        if ($this->traitHasRole($roles, $guard)) {
            return true;
        }

        $dbRole = $this->role;
        if (empty($dbRole)) {
            return false;
        }

        if (is_string($roles) && strpos($roles, '|') !== false) {
            $roles = explode('|', $roles);
        }

        $normalizeRoleName = function ($name) {
            $name = trim($name);
            if (strcasecmp($name, 'Managing Admin') === 0 || strcasecmp($name, 'Managing Editor') === 0) {
                return 'managing_editor_admin';
            }
            return strtolower($name);
        };

        $dbRoleNormalized = $normalizeRoleName($dbRole);

        if (is_string($roles)) {
            return $dbRoleNormalized === $normalizeRoleName($roles);
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if (is_string($role) && $dbRoleNormalized === $normalizeRoleName($role)) {
                    return true;
                }
                if ($role instanceof \Spatie\Permission\Models\Role && $dbRoleNormalized === $normalizeRoleName($role->name)) {
                    return true;
                }
            }
            return false;
        }

        if ($roles instanceof \Spatie\Permission\Models\Role) {
            return $dbRoleNormalized === $normalizeRoleName($roles->name);
        }

        return false;
    }

    public function scopeRole($query, $roles, $guard = null, $without = false)
    {
        if (is_string($roles) && strpos($roles, '|') !== false) {
            $roles = explode('|', $roles);
        }
        $rolesArray = \Illuminate\Support\Arr::wrap($roles);
        $roleNames = [];
        foreach ($rolesArray as $role) {
            if (is_string($role)) {
                $roleNames[] = $role;
            } elseif ($role instanceof \Spatie\Permission\Models\Role) {
                $roleNames[] = $role->name;
            }
        }

        if (in_array('Managing Editor', $roleNames) && !in_array('Managing Admin', $roleNames)) {
            $roleNames[] = 'Managing Admin';
        }
        if (in_array('Managing Admin', $roleNames) && !in_array('Managing Editor', $roleNames)) {
            $roleNames[] = 'Managing Editor';
        }

        if ($without) {
            return $query->whereDoesntHave('roles', function ($subQuery) use ($roleNames, $guard) {
                $subQuery->whereIn('name', $roleNames)
                         ->when($guard, fn ($query) => $query->where('guard_name', $guard));
            })->whereNotIn('role', $roleNames);
        }

        return $query->where(function ($q) use ($roleNames, $guard) {
            $q->whereHas('roles', function ($subQuery) use ($roleNames, $guard) {
                $subQuery->whereIn('name', $roleNames)
                         ->when($guard, fn ($query) => $query->where('guard_name', $guard));
            })->orWhereIn('role', $roleNames);
        });
    }
}
