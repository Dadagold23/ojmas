<?php

namespace Database\Seeders;

use App\Models\PaymentCategory;
use Illuminate\Database\Seeder;

class PaymentCategorySeeder extends Seeder
{
    public function run(): void
    {
        PaymentCategory::updateOrCreate(
            ['slug' => 'vetting-fee'],
            [
                'name' => 'Vetting Fee',
                'default_amount' => 15000,
                'currency' => 'NGN',
                'is_active' => true,
            ]
        );

        PaymentCategory::updateOrCreate(
            ['slug' => 'publication-fee'],
            [
                'name' => 'Publication Fee',
                'default_amount' => 50000,
                'currency' => 'NGN',
                'is_active' => true,
            ]
        );
    }
}
