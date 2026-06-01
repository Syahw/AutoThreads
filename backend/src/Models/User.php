<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'uuid', 'email', 'password_hash', 'name',
        'role', 'plan', 'is_active', 'settings',
        'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function niches()
    {
        return $this->hasMany(Niche::class);
    }

    public function generatedPosts()
    {
        return $this->hasMany(GeneratedPost::class);
    }

    public function scheduledPosts()
    {
        return $this->hasMany(ScheduledPost::class);
    }

    public function affiliateLinks()
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function threadsAccounts()
    {
        return $this->hasMany(ThreadsAccount::class);
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'plan' => $this->plan,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
