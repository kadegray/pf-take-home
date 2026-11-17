<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'generation_id',
        'type',
        'amount_micros',
        'balance_after_micros',
    ];

    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount_micros' => 'integer',
            'balance_after_micros' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
