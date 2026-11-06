<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotificationDispatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'admin_name',
        'admin_email',
        'title',
        'message',
        'type',
        'priority',
        'target_type',
        'target_count',
        'target_summary',
        'target_user_ids',
        'link_text',
        'link_href',
    ];

    protected function casts(): array
    {
        return [
            'target_count' => 'integer',
            'target_user_ids' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}

