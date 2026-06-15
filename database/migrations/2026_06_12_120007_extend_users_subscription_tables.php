<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1 收尾：补全 users / subscription_plans / user_subscriptions 字段
 * 与 er-diagram.md §2.1 / §2.7 / §2.8 一致
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. users 表扩展（locale、is_admin、default_shipping_address）
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 8)->default('zh-HK')->after('email_verified_at');
            $table->boolean('is_admin')->default(0)->after('locale');
            $table->json('default_shipping_address')->nullable()->after('is_admin');
            $table->index('locale');
        });

        // 2. subscription_plans 表扩展（cycle、is_active、image、features json）
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->enum('cycle', ['weekly', 'biweekly', 'monthly'])->default('weekly')->after('duration');
            $table->boolean('is_active')->default(1)->after('cycle');
            $table->string('image')->nullable()->after('is_active');
            $table->json('features')->nullable()->after('image');
            $table->index('is_active');
        });

        // 3. user_subscriptions 表扩展（next_fulfillment_at、auto_renew、cancel_reason）
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->date('next_fulfillment_at')->nullable()->after('end_date');
            $table->boolean('auto_renew')->default(1)->after('next_fulfillment_at');
            $table->text('cancel_reason')->nullable()->after('auto_renew');
            $table->index('status');
            $table->index('next_fulfillment_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['next_fulfillment_at']);
            $table->dropColumn(['next_fulfillment_at', 'auto_renew', 'cancel_reason']);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['cycle', 'is_active', 'image', 'features']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['locale']);
            $table->dropColumn(['locale', 'is_admin', 'default_shipping_address']);
        });
    }
};
