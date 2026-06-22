<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticlePublication extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id', 'published_by', 'published_on', 'published_pdf_path', 'note',
    ];

    protected function casts(): array
    {
        return [
            'published_on' => 'datetime',
        ];
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
