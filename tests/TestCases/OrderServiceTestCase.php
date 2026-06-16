<?php

namespace Tests\TestCases;

use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrderService 测试抽象基类（ADR-007 P1-1 模式 A）
 *
 * 所有 OrderService 相关测试共享：
 * - RefreshDatabase（每个测试方法独立事务）
 * - 已注入 OrderService（app() 解析）
 * - 测试用 User（基础用户，非 admin）
 * - 测试用 Product（库存 100，单价 50，HKD 计价）
 *
 * 子类直接用 $this->service / $this->user / $this->product。
 * 5 处 setUp 复制粘贴 → 1 处（基类）。
 */
abstract class OrderServiceTestCase extends TestCase
{
    use RefreshDatabase;

    protected OrderService $service;

    protected User $user;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderService::class);
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create(['stock' => 100, 'price' => 50]);
    }
}
