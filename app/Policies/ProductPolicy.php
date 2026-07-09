<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /** 登录用户可以查看管理列表（数据过滤由 Controller 负责） */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** 登录用户可以创建产品 */
    public function create(User $user): bool
    {
        return true;
    }

    /** admin 或产品所有者可以编辑 */
    public function update(User $user, Product $product): bool
    {
        return $user->is_admin || $product->user_id === $user->id;
    }
}
