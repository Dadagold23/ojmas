<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Volume extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'year',
        'status',
        'description',
        'slug',
        'volume_no',
    ];


    public function issues()
    {
        return $this->hasMany(\App\Models\Issue::class);
    }

}
