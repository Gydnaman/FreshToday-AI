<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_no',
        'status',
        'total_price',
        'discount_amount',
        'shipping_address',
        'tracking_no',
        'user_subscription_id',
        'placed_at',
        'paid_at',
        'cancelled_at',
        'delivered_at',
        'refunded_at',
        'cancel_reason',
        'refund_reason',
    ];

    protected $casts = [
        'status'           => OrderStatus::class,
        'shipping_address' => 'array',
        'total_price'      => 'decimal:2',
        'discount_amount'  => 'decimal:2',
        'placed_at'        => 'datetime',
        'paid_at'          => 'datetime',
        'cancelled_at'     => 'datetime',
        'delivered_at'     => 'datetime',
        'refunded_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_product')
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('created_at');
    }

    public function userSubscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class);
    }

    /**
     * 计算订单总金额（含税/运费简化为 0）
     *
     * @deprecated 当前未在生产路径调用。计划于 Sprint 2 接入「管理员手工修正订单」功能时启用，
     *             届时需同步 OrderService::createOrder 改为调用此方法以保持 SSOT。
     */
    public function recalculateTotal(): float
    {
        return (float) $this->products->sum(fn ($p) => $p->pivot->price * $p->pivot->quantity);
    }
}
