<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class Niche extends Model
{
    protected $table = 'niches';

    protected $fillable = [
        'user_id', 'name', 'slug', 'description',
        'keywords', 'target_audience', 'is_active', 'post_count',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function generatedPosts()
    {
        return $this->hasMany(GeneratedPost::class);
    }

    public function affiliateLinks()
    {
        return $this->hasMany(AffiliateLink::class);
    }
}
