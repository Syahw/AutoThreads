<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedPost extends Model
{
    protected $table = 'generated_posts';

    protected $fillable = [
        'user_id', 'niche_id', 'topic_id', 'affiliate_link_id',
        'prompt_template_id', 'content', 'hook', 'cta', 'hashtags',
        'category', 'tone', 'writing_style', 'quality_score',
        'humanization_score', 'status', 'ai_model', 'tokens_used',
        'generation_cost', 'variations_count', 'parent_post_id', 'metadata',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'metadata' => 'array',
        'quality_score' => 'float',
        'humanization_score' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function niche()
    {
        return $this->belongsTo(Niche::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function affiliateLink()
    {
        return $this->belongsTo(AffiliateLink::class);
    }

    public function scheduledPost()
    {
        return $this->hasOne(ScheduledPost::class);
    }

    public function variations()
    {
        return $this->hasMany(self::class, 'parent_post_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_post_id');
    }
}
