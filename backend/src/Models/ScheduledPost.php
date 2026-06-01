<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledPost extends Model
{
    protected $table = 'scheduled_posts';

    protected $fillable = [
        'user_id', 'generated_post_id', 'threads_account_id',
        'scheduled_at', 'status', 'retry_count', 'max_retries',
        'last_error', 'posted_at', 'threads_post_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function generatedPost()
    {
        return $this->belongsTo(GeneratedPost::class);
    }

    public function threadsAccount()
    {
        return $this->belongsTo(ThreadsAccount::class);
    }

    public function analytics()
    {
        return $this->hasMany(Analytics::class);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'queued')
            ->where('scheduled_at', '<=', now());
    }
}
