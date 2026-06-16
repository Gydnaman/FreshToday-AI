<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'value', 'min_order_amount',
        'valid_from', 'valid_until', 'usage_limit', 'used_count', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isValidForAmount(float $amount): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && now()->gt($this->valid_until)) {
            return false;
        }
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return $amount >= (float) $this->min_order_amount;
    }

    public function discountAmount(float $orderTotal): float
    {
        return $this->type === 'percent'
            ? round($orderTotal * (float) $this->value / 100, 2)
            : (float) $this->value;
    }
}
