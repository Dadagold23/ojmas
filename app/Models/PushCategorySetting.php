<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushCategorySetting extends Model
{
    protected $fillable = [
        'category', 'label', 'is_eligible', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_eligible' => 'boolean',
        ];
    }
}
