<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Exceptions\GuardFailedException;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;
    private User $user;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionService::class);
        $this->user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 100, 'price' => 50]);
        $this->plan = SubscriptionPlan::factory()->create([
            'duration' => 30,
            'cycle'    => 'weekly',
            'is_active'=> true,
        ]);
        $this->plan->products()->attach($product->id, ['quantity' => 2, 'price' => 50]);
        $this->plan->refresh();
    }

    /** 成功订阅 */
    public function test_subscribe_creates_active_subscription(): void
    {
        $sub = $this->service->subscribe($this->user, $this->plan, Carbon::today(), true);

        $this->assertEquals('active', $sub->status);
        $this->assertEquals($this->plan->id, $sub->subscription_plan_id);
        $this->assertTrue($sub->auto_renew);
    }

    /** GUARD-SUB：已有 active 订阅时拒绝 */
    public function test_subscribe_rejects_when_already_active(): void
    {
        $this->service->subscribe($this->user, $this->plan, Carbon::today(), true);

        $this->expectException(GuardFailedException::class);
        $this->service->subscribe($this->user, $this->plan, Carbon::today(), true);
    }

    /** 取消订阅 */
    public function test_cancel_subscription_succeeds(): void
    {
        $sub = $this->service->subscribe($this->user, $this->plan, Carbon::today(), true);
        $cancelled = $this->service->cancel($sub, 'change_mind');

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertEquals('change_mind', $cancelled->cancel_reason);
    }

    /** 重复取消抛 GuardFailedException */
    public function test_double_cancel_rejected(): void
    {
        $sub = $this->service->subscribe($this->user, $this->plan, Carbon::today(), true);
        $this->service->cancel($sub, 'first');

        $this->expectException(GuardFailedException::class);
        $this->service->cancel($sub->fresh(), 'second');
    }
}
