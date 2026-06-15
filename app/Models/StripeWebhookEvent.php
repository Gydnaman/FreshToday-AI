<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeWebhookEvent extends Model
{
    protected $fillable = [
        'provider', 'provider_event_id', 'event_type', 'payload', 'signature',
        'received_at', 'processed_at', 'status', 'attempts', 'last_error',
        'related_payment_id', 'related_order_id',
    ];

    protected $casts = [
        'payload'     => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'related_payment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }
}
