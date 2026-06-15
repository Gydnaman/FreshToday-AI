<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-15 补：orders 表加 refunded_at + refund_reason
 *
 * 背景：OrderService::transition() 进入 Refunded 时需要写：
 *   - refunded_at     (datetime, 退款时间)
 *   - refund_reason   (string, 退款原因)
 *
 * 这两个字段在 Sprint 1 Day 1 create migration 时被遗漏（refund 路径是 Day 4 实现的），
 * 后续 Sprint 1 Day 5 测试中又未触发到（测试都是测 cancel/paid 路径），
 * 导致 Sprint 2 跑退款测试时 QueryException。
 *
 * 关联代码：
 *   - app/Models/Order.php            $fillable / $casts
 *   - app/Services/OrderService.php   transition(Refunded) 分支
 *   - tests/Unit/Services/OrderServiceRefundTest.php
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('delivered_at')->comment('退款完成时间');
            $table->string('refund_reason', 255)->nullable()->after('refunded_at')->comment('退款原因（来自 status log context）');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_at', 'refund_reason']);
        });
    }
};
