<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorrespondenceMessage extends Model
{
    use HasFactory;

    protected $fillable = [
    'submission_id',
    'type',
    'subject',
    'message',
    'recipient_email',
    'status',
];



    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(\App\Models\Submission::class);
    }


    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(\App\Models\User::class, 'recipient_id');
    }
    
}
