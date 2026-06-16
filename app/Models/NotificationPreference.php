<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'email_order', 'email_menu', 'email_promo',
        'sms_order', 'push_enabled', 'quiet_hours_start', 'quiet_hours_end',
    ];

    protected $casts = [
        'email_order' => 'boolean',
        'email_menu' => 'boolean',
        'email_promo' => 'boolean',
        'sms_order' => 'boolean',
        'push_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
