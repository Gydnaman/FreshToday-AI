<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 2: payments + stripe_webhook_events 表
 * 详见 docs/bmad/er-diagram.md §2.12 / §2.17
 * 详见 docs/bmad/api-contract.md §2.8 Webhook
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->comment('stripe/payme/alipay_hk');
            $table->string('provider_txn_id', 128)->unique();
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('HKD');
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending');
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index('order_id');
            $table->index('status');
        });

        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_event_id', 128)->unique()->comment('evt_xxx 去重主键');
            $table->string('event_type', 64);
            $table->json('payload');
            $table->string('signature', 255)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->enum('status', ['received', 'processing', 'processed', 'failed', 'ignored'])
                ->default('received');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('related_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('related_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            $table->index(['provider', 'event_type']);
            $table->index('status');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('payments');
    }
};
