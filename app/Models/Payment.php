<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id', 'provider', 'provider_txn_id', 'amount',
        'currency', 'status', 'raw_response', 'paid_at', 'refunded_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'raw_response' => 'array',
        'paid_at'      => 'datetime',
        'refunded_at'  => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(StripeWebhookEvent::class, 'related_payment_id');
    }
}
