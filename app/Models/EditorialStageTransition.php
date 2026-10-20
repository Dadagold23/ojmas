<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditorialStageTransition extends Model
{
    protected $fillable = [
        'from_stage_slug', 'to_stage_slug', 'label', 'required_permissions',
        'requires_deadline', 'requires_note', 'notifies_author',
        'opens_revision_window', 'closes_revision_window',
    ];

    protected function casts(): array
    {
        return [
            'required_permissions' => 'array',
            'requires_deadline' => 'boolean',
            'requires_note' => 'boolean',
            'notifies_author' => 'boolean',
            'opens_revision_window' => 'boolean',
            'closes_revision_window' => 'boolean',
        ];
    }

    public function fromStage()
    {
        return $this->belongsTo(EditorialStage::class, 'from_stage_slug', 'slug');
    }

    public function toStage()
    {
        return $this->belongsTo(EditorialStage::class, 'to_stage_slug', 'slug');
    }
}