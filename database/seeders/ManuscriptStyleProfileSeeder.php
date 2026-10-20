<?php

namespace Database\Seeders;

use App\Services\Documents\ManuscriptProcessingService;
use Illuminate\Database\Seeder;

/**
 * Seeds the configurable journal style profiles so a Super Admin can view,
 * duplicate and adjust them from the administration screens.
 */
class ManuscriptStyleProfileSeeder extends Seeder
{
    public function run(): void
    {
        $created = app(ManuscriptProcessingService::class)->seedStyleProfiles();

        $this->command?->info("Seeded {$created} manuscript style profile(s).");
    }
}