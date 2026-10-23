<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'asset',
        'amount',
        'value_usd',
        'fee_usd',
        'status',
        'tx_hash',
        'network',
        'address',
        'confirmations',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'value_usd' => 'float',
            'fee_usd' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
