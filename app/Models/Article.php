<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'author_id',
        'issue_id',
        'title',
        'slug',
        'doi',
        'abstract',
        'keywords',
        'subject_area',
        'full_text',
        'pages',
        'issue_order',
        'licence',
        'copyright_note',
        'published_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'date',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(\App\Models\Submission::class);
    }

    public function issue()
    {
        return $this->belongsTo(\App\Models\Issue::class);
    }

    public function author()
    {
        return $this->hasOneThrough(
            \App\Models\User::class,
            \App\Models\Submission::class,
            'id',        // submissions.id
            'id',        // users.id
            'submission_id', // articles.submission_id
            'author_id'  // submissions.author_id
        );
    }

    public function publication()
    {
        return $this->hasOne(ArticlePublication::class);
    }


}
