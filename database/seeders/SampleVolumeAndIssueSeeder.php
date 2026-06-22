<?php

namespace Database\Seeders;

use App\Models\Issue;
use App\Models\Volume;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArchiveSeeder extends Seeder
{
    public function run(): void
    {
        $volume = Volume::firstOrCreate(
            ['volume_no' => 1, 'year' => now()->year],
            [
                'title' => 'Volume 1',
                'slug' => 'volume-1-' . now()->year,
                'description' => 'Inaugural volume',
                'is_active' => true,
            ]
        );

        Issue::firstOrCreate(
            ['volume_id' => $volume->id, 'issue_no' => 1],
            [
                'title' => 'Issue 1',
                'slug' => 'issue-1-' . Str::lower(Str::random(4)),
                'description' => 'First published issue',
                'status' => 'draft',
            ]
        );
    }
}
