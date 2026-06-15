<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'subscription_plan_id',
        'start_date', 'end_date', 'next_fulfillment_at',
        'auto_renew', 'status', 'cancel_reason',
    ];

    protected $casts = [
        'start_date'          => 'date',
        'end_date'            => 'date',
        'next_fulfillment_at' => 'date',
        'auto_renew'          => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /** 计划是否仍有效 */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && (! $this->end_date || $this->end_date->gte(now()));
    }
}
