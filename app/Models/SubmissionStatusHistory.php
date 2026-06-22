<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubmissionStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'from_status',
        'to_status',
        'remarks',
        'changed_by',
        'old_status',
        'new_status',
        'note'
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}
