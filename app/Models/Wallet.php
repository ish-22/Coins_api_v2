<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'asset_id',
        'available_balance',
        'locked_balance',
    ];

    protected function casts(): array
    {
        return [
            'available_balance' => 'float',
            'locked_balance' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function getTotalBalanceAttribute(): float
    {
        return $this->available_balance + $this->locked_balance;
    }
}
