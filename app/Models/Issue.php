<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'volume_id',
        'issue_no',
        'title',
        'status',
        'published_on',
        'description',
        'pdf_path',
        'slug',
    ];


    protected function casts(): array
    {
        return [
            'published_on' => 'date',
        ];
    }

    public function volume()
    {
        return $this->belongsTo(\App\Models\Volume::class);
    }

    public function articles()
    {
        return $this->hasMany(\App\Models\Article::class);
    }


}
