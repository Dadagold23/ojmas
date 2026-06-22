<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('users:sync-legacy-roles', function () {
    $priority = ['Super Admin', 'Admin', 'Managing Editor', 'Editor', 'Reviewer', 'Author'];

    $updated = 0;
    $unchanged = 0;
    $skipped = 0;

    User::with('roles')
        ->orderBy('id')
        ->chunkById(100, function ($users) use ($priority, &$updated, &$unchanged, &$skipped) {
            foreach ($users as $user) {
                $roleNames = $user->roles->pluck('name')->all();

                if (empty($roleNames)) {
                    $skipped++;
                    $this->warn("Skipped user {$user->id} ({$user->email}): no assigned role found.");
                    continue;
                }

                $resolvedRole = collect($priority)
                    ->first(fn (string $roleName) => in_array($roleName, $roleNames, true))
                    ?? $roleNames[0];

                if ($user->role === $resolvedRole) {
                    $unchanged++;
                    continue;
                }

                $previousRole = $user->role;
                $user->syncLegacyRoleColumn($resolvedRole);
                $updated++;

                $this->line("Updated user {$user->id} ({$user->email}): {$previousRole} -> {$resolvedRole}");
            }
        });

    $this->newLine();
    $this->info("Legacy role sync complete. Updated: {$updated}, unchanged: {$unchanged}, skipped: {$skipped}.");
})->purpose('Backfill users.role from assigned Spatie roles');
