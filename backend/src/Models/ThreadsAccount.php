<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class ThreadsAccount extends Model
{
    protected $table = 'threads_accounts';

    protected $fillable = [
        'user_id',
        'threads_user_id',
        'username',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active',
        'metadata',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function toPublicArray(): array
    {
        $metadata = $this->metadata ?? [];

        return [
            'id' => $this->id,
            'threads_user_id' => $this->threads_user_id,
            'username' => $this->username,
            'is_active' => $this->is_active,
            'token_expires_at' => $this->token_expires_at?->toISOString(),
            'connected_at' => $this->created_at?->toISOString(),
            'token_scopes' => $metadata['token_scopes'] ?? [],
            'can_publish_reply_chain' => $metadata['can_publish_reply_chain'] ?? false,
        ];
    }
}
