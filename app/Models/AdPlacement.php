<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPlacement extends Model
{
    protected $fillable = [
        'key', 'label', 'description', 'is_active', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function campaigns()
    {
        return $this->hasMany(\App\Models\AdCampaign::class, 'placement_id');
    }
}
