<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'incident_id',
        'title',
        'date_display',
        'severity',
        'status',
        'affected_services',
        'description',
        'resolution_summary',
        'updates',
    ];

    protected $casts = [
        'affected_services' => 'array',
        'updates' => 'array',
    ];
}
