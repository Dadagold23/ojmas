<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_name', 'acronym', 'issn_print', 'issn_online', 'publisher_name',
        'contact_email', 'support_phone', 'submission_fee', 'publication_fee',
        'address', 'logo', 'favicon', 'copyright_text',
        'orcid_enabled', 'orcid_sandbox', 'orcid_required_for_new_authors',
    ];

    protected function casts(): array
    {
        return [
            'orcid_enabled' => 'boolean',
            'orcid_sandbox' => 'boolean',
            'orcid_required_for_new_authors' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->orderBy('id')->first() ?? self::create([]);
    }
}
