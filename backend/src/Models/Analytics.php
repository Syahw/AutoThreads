<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
    protected $table = 'analytics';

    protected $fillable = [
        'user_id', 'scheduled_post_id', 'generated_post_id', 'threads_post_id',
        'impressions', 'likes', 'comments', 'reposts', 'quotes',
        'link_clicks', 'profile_visits', 'followers_gained',
        'ctr', 'engagement_rate', 'collected_at',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'ctr' => 'float',
        'engagement_rate' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledPost()
    {
        return $this->belongsTo(ScheduledPost::class);
    }

    public function generatedPost()
    {
        return $this->belongsTo(GeneratedPost::class);
    }
}
