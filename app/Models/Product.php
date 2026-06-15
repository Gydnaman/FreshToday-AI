<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'price',
        'image',
        'carbon_footprint',
        'stock',
        'category_id',
        'is_organic',
        'origin',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'carbon_footprint' => 'decimal:3',
        'is_organic'       => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_product')
            ->withPivot('quantity', 'price')
            ->withTimestamps();
    }

    public function subscriptionPlans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'subscription_plan_product')
            ->withTimestamps();
    }

    /** 是否有可用库存（业务层再读锁，调用方负责事务） */
    public function hasStock(int $qty): bool
    {
        return $this->stock >= $qty;
    }
}
