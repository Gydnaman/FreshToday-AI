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
        'user_id',
        'is_organic',
        'origin',
        'status',
    ];

    protected $appends = ['image_url'];

    protected $casts = [
        'price' => 'decimal:2',
        'carbon_footprint' => 'decimal:3',
        'is_organic' => 'boolean',
        'stock' => 'integer',
    ];

    /** 公开 /api/products 可见的产品状态 */
    public const STATUS_PUBLISHED = 'published';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ARCHIVED = 'archived';

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * 返回可在 <img src="..."> 中直接使用的图片 URL。
     * 外部 URL 直接返回；本地上传路径自动拼接 /storage 前缀。
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return asset('storage/'.$this->image);
    }
}
