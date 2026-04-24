<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    public function products()
    {
        return $this->belongsToMany(Product::class, 'subscription_plan_product')->withTimestamps();
    }

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }
}
