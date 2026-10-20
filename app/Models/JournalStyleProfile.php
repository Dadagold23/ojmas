<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Configurable manuscript style profile.
 *
 * The two profiles seeded by the system (APA 7 professional and OJMAS) can be
 * duplicated and adjusted here by a Super Admin without a deployment. The
 * stored rules override the built-in definition key by key, so adding a new
 * requirement to the built-in profile does not break a stored copy of it.
 */
class JournalStyleProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'version',
        'is_active',
        'rules_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requirements(): array
    {
        return is_array($this->rules_json) ? $this->rules_json : [];
    }
}