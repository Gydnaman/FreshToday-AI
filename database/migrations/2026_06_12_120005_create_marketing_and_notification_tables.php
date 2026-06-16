<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 3: coupons / user_coupons / points_transactions / notification_preferences
 * 详见 docs/bmad/er-diagram.md §2.13-§2.16
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->enum('type', ['fixed', 'percent']);
            $table->decimal('value', 10, 2);
            $table->decimal('min_order_amount', 10, 2)->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamp('used_at')->nullable();
            $table->enum('status', ['claimed', 'used', 'expired'])->default('claimed');
            $table->timestamps();
            $table->unique(['user_id', 'coupon_id']);
            $table->index('status');
        });

        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjust']);
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('reason', 255)->nullable();
            $table->foreignId('related_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index('user_id');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->boolean('email_order')->default(1);
            $table->boolean('email_menu')->default(1);
            $table->boolean('email_promo')->default(0);
            $table->boolean('sms_order')->default(0);
            $table->boolean('push_enabled')->default(1);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('points_transactions');
        Schema::dropIfExists('user_coupons');
        Schema::dropIfExists('coupons');
    }
};
