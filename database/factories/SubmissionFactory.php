<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->sentence(6), '.');

        return [
            'submission_no' => 'SUB-'.now()->format('Ymd').'-'.strtoupper(fake()->bothify('??????')),
            'author_id' => \App\Models\User::factory(),
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.strtolower(fake()->bothify('?????')),
            'abstract' => fake()->paragraph(),
            'keywords' => implode(', ', fake()->words(4)),
            'subject_area' => fake()->randomElement(['Education', 'Management', 'Computer Science', 'Arts']),
            'status' => 'submitted',
            'is_corresponding_author' => true,
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn () => ['status' => 'pending_payment']);
    }

    public function underReview(): static
    {
        return $this->state(fn () => ['status' => 'under_review']);
    }
}