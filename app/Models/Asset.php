<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'symbol',
        'name',
        'type',
        'color',
        'icon_bg',
        'networks',
        'price_usd',
        'change_24h',
        'volume_24h',
        'deposit_enabled',
        'withdraw_enabled',
        'trading_enabled',
        'min_deposit',
        'min_withdrawal',
        'withdrawal_fee',
    ];

    protected function casts(): array
    {
        return [
            'networks' => 'array',
            'price_usd' => 'float',
            'change_24h' => 'float',
            'volume_24h' => 'float',
            'deposit_enabled' => 'boolean',
            'withdraw_enabled' => 'boolean',
            'trading_enabled' => 'boolean',
            'min_deposit' => 'float',
            'min_withdrawal' => 'float',
            'withdrawal_fee' => 'float',
        ];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class, 'asset_id');
    }
}
