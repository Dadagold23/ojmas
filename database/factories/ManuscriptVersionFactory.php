<?php

namespace Database\Factories;

use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ManuscriptVersion>
 */
class ManuscriptVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'version_number' => 1,
            'label' => 'Version 1',
            'storage_path' => 'manuscripts/formated/placeholder-'.fake()->uuid().'.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => fake()->numberBetween(20000, 200000),
            'sha256' => hash('sha256', (string) fake()->uuid()),
            'format' => 'original',
            'origin' => 'author_upload',
            'created_by' => \App\Models\User::factory(),
        ];
    }

    public function formatted(): static
    {
        return $this->state(fn () => ['format' => 'formatted', 'origin' => 'apa7_format']);
    }
}