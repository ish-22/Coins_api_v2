<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemService extends Model
{
    protected $fillable = [
        'service_id',
        'name',
        'category',
        'status',
        'description',
        'uptime',
        'latency',
        'daily_uptime_history',
        'sort_order',
    ];

    protected $casts = [
        'daily_uptime_history' => 'array',
    ];
}
