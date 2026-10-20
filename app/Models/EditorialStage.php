<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'sort_order', 'is_initial', 'is_terminal',
        'requires_payment', 'requires_author_approval', 'blinded_at_this_stage',
        'default_deadline_days', 'required_permissions', 'legacy_status',
        'colour_hint', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_initial' => 'boolean',
            'is_terminal' => 'boolean',
            'requires_payment' => 'boolean',
            'requires_author_approval' => 'boolean',
            'blinded_at_this_stage' => 'boolean',
            'default_deadline_days' => 'integer',
            'required_permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function isBlinded(): bool
    {
        return (bool) $this->blinded_at_this_stage;
    }

    public function badgeClass(): string
    {
        $hint = trim((string) $this->colour_hint);

        return $hint !== '' && preg_match('/^[a-z-]+$/', $hint) === 1
            ? 'text-bg-'.$hint
            : 'text-bg-secondary';
    }
}