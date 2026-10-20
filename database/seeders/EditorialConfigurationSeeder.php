<?php

namespace Database\Seeders;

use App\Services\Permissions\PermissionRegistrar;
use App\Services\Workflow\EditorialWorkflowService;
use App\Models\DocumentRetentionPolicy;
use Illuminate\Database\Seeder;

/**
 * Seeds the journal's configuration baseline:
 *
 *  - the permission catalogue (additive; existing roles keep what they have),
 *  - the configurable editorial lifecycle,
 *  - the default document retention policy.
 *
 * Safe to run repeatedly.
 */
class EditorialConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $permissionResult = app(PermissionRegistrar::class)->sync();

        $this->command?->info(sprintf(
            'Permissions: %d new, %d role(s) created, %d granted.',
            $permissionResult['created_permissions'],
            $permissionResult['created_roles'],
            $permissionResult['granted'],
        ));

        $workflow = app(EditorialWorkflowService::class)->seed();

        $this->command?->info(sprintf(
            'Workflow: %d stage(s), %d transition(s) configured.',
            $workflow['stages'],
            $workflow['transitions'],
        ));

        DocumentRetentionPolicy::defaultPolicy();

        $this->command?->info('Retention: default policy present.');
    }
}