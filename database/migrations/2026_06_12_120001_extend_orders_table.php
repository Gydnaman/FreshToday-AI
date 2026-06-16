<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1: 补全 orders 表字段 + CHECK 约束
 * 详见 docs/bmad/er-diagram.md §2.6 与 order-state-machine.md §A.1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_no', 32)->nullable()->unique()->after('id')->comment('业务单号 GB20260612xxxxx');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('total_price');
            $table->json('shipping_address')->nullable()->after('discount_amount');
            $table->string('tracking_no', 64)->nullable()->after('shipping_address');
            $table->timestamp('placed_at')->nullable()->after('tracking_no');
            $table->timestamp('paid_at')->nullable()->after('placed_at');
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->timestamp('delivered_at')->nullable()->after('cancelled_at');
            $table->text('cancel_reason')->nullable()->after('delivered_at');
            // 2026-06-15 补：订单可关联订阅（订阅下单时携带 user_subscription_id + snapshot price）
            $table->unsignedBigInteger('user_subscription_id')->nullable()->after('user_id')->comment('关联订阅，nullable');
            $table->unsignedBigInteger('subscription_plan_id')->nullable()->after('user_subscription_id')->comment('订阅快照 plan，nullable');
            $table->string('source', 16)->default('cart')->after('subscription_plan_id')->comment('cart | subscription | direct');

            $table->index('status');
            $table->index(['user_id', 'status']);
            $table->index('user_subscription_id');
            $table->index('subscription_plan_id');
        });

        // 状态机 SSOT 在应用层 (OrderService::canTransition)，见 ADR-0005
        // MySQL 8.0.16+ 额外加 DB 层 CHECK 作为双保险；SQLite 跳过
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE orders
                ADD CONSTRAINT chk_orders_status
                CHECK (status IN ('pending','paid','processing','shipped','delivered','cancelled','refunded'))
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT chk_orders_status');
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['user_subscription_id']);
            $table->dropIndex(['subscription_plan_id']);
            $table->dropColumn([
                'order_no', 'discount_amount', 'shipping_address', 'tracking_no',
                'placed_at', 'paid_at', 'cancelled_at', 'delivered_at', 'cancel_reason',
                'user_subscription_id', 'subscription_plan_id', 'source',
            ]);
        });
    }
};
