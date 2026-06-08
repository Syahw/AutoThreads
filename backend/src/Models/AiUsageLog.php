<?php

namespace AutoThreads\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLog extends Model
{
    protected $table = 'ai_usage_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'model', 'action', 'prompt_tokens', 'completion_tokens',
        'total_tokens', 'cost', 'response_time_ms', 'success', 'error_message', 'created_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'cost' => 'float',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
