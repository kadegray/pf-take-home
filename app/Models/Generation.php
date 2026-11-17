<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    protected $fillable = [
        'user_id',
        'pixelforge_id',
        'idempotency_key',
        'prompt',
        'size',
        'quality',
        'reference_images',
        'status',
        'estimated_cost_micros',
        'actual_cost_micros',
        'usage',
        'rates',
        'error_code',
        'error_message',
        'image_url',
        'image_url_expires_at',
        'pixelforge_created_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_images' => 'array',
            'status' => GenerationStatus::class,
            'estimated_cost_micros' => 'integer',
            'actual_cost_micros' => 'integer',
            'usage' => 'array',
            'rates' => 'array',
            'image_url_expires_at' => 'datetime',
            'pixelforge_created_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
