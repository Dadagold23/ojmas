<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'reviewer_assignment_id',
        'recommendation',
        'comments_for_author',
        'comments_for_editor',
    ];

    public function assignment()
    {
        return $this->belongsTo(ReviewerAssignment::class, 'reviewer_assignment_id');
    }
}
