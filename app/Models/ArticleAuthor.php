<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleAuthor extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id','full_name','email','institution',
        'country','is_corresponding','author_order'
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}
