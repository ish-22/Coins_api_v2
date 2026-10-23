<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingPair extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'symbol',
        'base_asset',
        'quote_asset',
        'price',
        'change_24h',
        'high_24h',
        'low_24h',
        'volume_24h_base',
        'volume_24h_quote',
        'price_decimals',
        'amount_decimals',
        'min_amount',
        'maker_fee',
        'taker_fee',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'change_24h' => 'float',
            'high_24h' => 'float',
            'low_24h' => 'float',
            'volume_24h_base' => 'float',
            'volume_24h_quote' => 'float',
            'price_decimals' => 'integer',
            'amount_decimals' => 'integer',
            'min_amount' => 'float',
            'maker_fee' => 'float',
            'taker_fee' => 'float',
        ];
    }
}
