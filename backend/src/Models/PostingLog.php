<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class PostingLog extends Model
{
    protected $table = 'posting_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'scheduled_post_id', 'threads_account_id',
        'action', 'status', 'threads_post_id',
        'request_payload', 'response_payload',
        'error_message', 'response_time_ms', 'created_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledPost()
    {
        return $this->belongsTo(ScheduledPost::class);
    }
}
