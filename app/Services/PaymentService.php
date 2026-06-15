<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\GuardFailedException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 支付编排服务（Day 2-3）
 * 详见 docs/bmad/api-contract.md §2.4 / §2.8
 */
class PaymentService
{
    /**
     * 创建支付意图（同步返回 redirect_url；webhook 异步更新）
     */
    public function createIntent(Order $order, string $provider, string $returnUrl): Payment
    {
        if (! in_array($order->status, [OrderStatus::Pending], true)) {
            throw new GuardFailedException('GUARD-P1', '订单状态不允许支付', [
                'current' => $order->status->value,
                'allowed' => ['pending'],
            ]);
        }

        return DB::transaction(function () use ($order, $provider, $returnUrl) {
            $payment = Payment::create([
                'order_id'        => $order->id,
                'provider'        => $provider,
                'provider_txn_id' => 'pending_' . $order->order_no, // 网关回调时回填
                'amount'          => $order->total_price,
                'currency'        => 'HKD',
                'status'          => 'pending',
            ]);

            // 调用网关（mock/stripe/payme 统一入口）
            $redirectUrl = $this->callGatewayCreate($provider, $payment, $returnUrl);
            $payment->update(['provider_txn_id' => $this->extractTxnId($provider, $redirectUrl) ?? $payment->provider_txn_id]);

            return $payment;
        });
    }

    /**
     * Webhook 入口（不依赖 auth，靠签名校验 + 去重表）
     * @see docs/bmad/api-contract.md §2.8
     */
    public function handleWebhook(string $provider, array $payload, ?string $signature = null): void
    {
        // 1. 验签（这里仅占位；实际在 Controller 用 Provider SDK 验签）
        $eventId = $payload['id'] ?? $payload['event_id'] ?? null;
        if (! $eventId) {
            Log::warning('Webhook missing event id', ['provider' => $provider]);
            return;
        }

        // 2. 落库去重（StripeWebhookEvent.provider_event_id UQ）
        $event = \App\Models\StripeWebhookEvent::firstOrCreate(
            ['provider_event_id' => $eventId],
            [
                'provider'    => $provider,
                'event_type'  => $payload['type'] ?? 'unknown',
                'payload'     => $payload,
                'signature'   => $signature,
                'received_at' => now(),
                'status'      => 'received',
            ],
        );

        // 幂等：已存在则直接返回
        if (! $event->wasRecentlyCreated && in_array($event->status, ['processed', 'ignored'], true)) {
            Log::info('Webhook already processed', ['event_id' => $eventId]);
            return;
        }

        // 3. 路由到具体业务
        try {
            $event->update(['status' => 'processing', 'attempts' => $event->attempts + 1]);
            $this->routeEvent($event);
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        } catch (\Throwable $e) {
            $event->update([
                'status'     => 'failed',
                'last_error' => $e->getMessage(),
            ]);
            Log::error('Webhook processing failed', [
                'event_id' => $eventId, 'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /** 退款 */
    public function refund(Payment $payment, int $amountHkd, string $reason): bool
    {
        $order = $payment->order;
        if (! $order->status->canBeRefunded()) {
            throw new GuardFailedException('GUARD-P2', '订单当前状态不允许退款', [
                'current' => $order->status->value,
            ]);
        }

        // 1. 调网关退款（mock：直接成功）
        // 2. 写 payment status=refunded
        $payment->update([
            'status'       => 'refunded',
            'refunded_at'  => now(),
        ]);

        // 3. 触发状态机
        app(OrderService::class)->transition(
            $order,
            OrderStatus::Refunded,
            'payment_refunded',
            ['reason' => $reason, 'amount' => $amountHkd, 'actor_type' => 'system'],
        );

        return true;
    }

    private function routeEvent(\App\Models\StripeWebhookEvent $event): void
    {
        $payload = $event->payload;
        $orderService = app(OrderService::class);

        match ($event->event_type) {
            'payment_intent.succeeded' => $this->onPaymentSucceeded($event, $payload),
            'payment_intent.payment_failed' => $this->onPaymentFailed($event, $payload),
            'charge.refunded' => $this->onChargeRefunded($event, $payload),
            default => $event->update(['status' => 'ignored']),
        };
    }

    private function onPaymentSucceeded(\App\Models\StripeWebhookEvent $event, array $payload): void
    {
        $txnId = $payload['data']['object']['id'] ?? null;
        $payment = Payment::where('provider_txn_id', $txnId)->first();
        if (! $payment) {
            Log::warning('Webhook: payment not found', ['txn_id' => $txnId]);
            return;
        }

        $payment->update([
            'status'     => 'succeeded',
            'paid_at'    => now(),
            'raw_response' => $payload,
        ]);
        $event->update(['related_payment_id' => $payment->id, 'related_order_id' => $payment->order_id]);

        // 触发状态机
        app(OrderService::class)->transition(
            $payment->order,
            OrderStatus::Paid,
            'payment_succeeded',
            ['payment' => $payment, 'actor_type' => 'webhook'],
        );
    }

    private function onPaymentFailed(\App\Models\StripeWebhookEvent $event, array $payload): void
    {
        $txnId = $payload['data']['object']['id'] ?? null;
        $payment = Payment::where('provider_txn_id', $txnId)->first();
        if ($payment) {
            $payment->update(['status' => 'failed']);
        }
    }

    private function onChargeRefunded(\App\Models\StripeWebhookEvent $event, array $payload): void
    {
        $txnId = $payload['data']['object']['payment_intent'] ?? null;
        $payment = Payment::where('provider_txn_id', $txnId)->first();
        if (! $payment) return;
        $event->update(['related_payment_id' => $payment->id, 'related_order_id' => $payment->order_id]);
        // OrderService 已在 PaymentService::refund 中转移
    }

    private function callGatewayCreate(string $provider, Payment $payment, string $returnUrl): string
    {
        // Sprint 1 占位：返回 mock URL
        return $returnUrl . '?payment_id=' . $payment->id;
    }

    private function extractTxnId(string $provider, string $redirectUrl): ?string
    {
        // 实际从网关响应提取
        return null;
    }
}
