<?php

namespace Database\Seeders;

use App\Models\JournalSetting;
use Illuminate\Database\Seeder;

class JournalSettingSeeder extends Seeder
{
    public function run(): void
    {
        JournalSetting::updateOrCreate(
            ['id' => 1],
            [
                'site_name' => 'OJMAS',
                'acronym' => 'OJMAS',
                'publisher_name' => 'Offa Journal of Multidisciplinary and Applied Sciences',
                'contact_email' => 'support@ojmas.test',
                'support_phone' => '+2348000000000',
                'submission_fee' => 15000,
                'publication_fee' => 50000,
                'address' => 'Offa, Kwara State, Nigeria',
                'copyright_text' => 'Â© OJMAS',
            ]
        );
    }
}
