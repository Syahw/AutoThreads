<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateLink extends Model
{
    protected $table = 'affiliate_links';

    protected $fillable = [
        'user_id', 'niche_id', 'product_name', 'url', 'short_url',
        'cta_style', 'campaign_tag', 'tracking_params',
        'click_count', 'conversion_count', 'is_active', 'expires_at',
    ];

    protected $casts = [
        'tracking_params' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function niche()
    {
        return $this->belongsTo(Niche::class);
    }
}
