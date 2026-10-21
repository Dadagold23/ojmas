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
        'cover_image_path',
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

    /**
     * The issue's articles in the journal's approved editorial sequence.
     *
     * Order is the explicit `issue_order` an editor sets; articles without one
     * come after ordered ones, then by publication date and id — deterministic,
     * never arbitrary-looking, and never reshuffling what an editor recorded.
     */
    public function orderedArticles()
    {
        return $this->articles()
            ->orderByRaw('issue_order IS NULL, issue_order ASC, published_on IS NULL, published_on ASC, id ASC');
    }


}
