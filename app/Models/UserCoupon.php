<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCoupon extends Model
{
    protected $fillable = ['user_id', 'coupon_id', 'order_id', 'claimed_at', 'used_at', 'status'];

    protected $casts = [
        'claimed_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user(): BelongsTo  { return $this->belongsTo(User::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function order(): BelongsTo  { return $this->belongsTo(Order::class); }
}
