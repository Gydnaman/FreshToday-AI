<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use App\Models\Order;
use RuntimeException;

/**
 * 状态机非法转移异常
 * 由 OrderService::transition() 抛出，被 reviewer-agent 列入 P0 #1 必修
 */
class InvalidTransitionException extends RuntimeException
{
    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly string $trigger,
    ) {
        parent::__construct(sprintf(
            'Invalid order transition: order=%d from=%s to=%s trigger=%s',
            $order->id,
            $from->value,
            $to->value,
            $trigger,
        ));
    }

    /** API 层映射为 422 BUSINESS_RULE */
    public function toApiPayload(): array
    {
        return [
            'code' => 'BUSINESS_RULE',
            'message' => '订单状态不允许此操作',
            'details' => [
                'current' => $this->from->value,
                'attempted_to' => $this->to->value,
                'allowed_from' => $this->allowedFromStates(),
            ],
        ];
    }

    /** 返回当前状态下合法的来源状态集合（用于 API 错误明细） */
    private function allowedFromStates(): array
    {
        return match ($this->to) {
            OrderStatus::Paid => [OrderStatus::Pending->value],
            OrderStatus::Cancelled => [OrderStatus::Pending->value],
            OrderStatus::Processing => [OrderStatus::Paid->value],
            OrderStatus::Shipped => [OrderStatus::Processing->value],
            OrderStatus::Delivered => [OrderStatus::Shipped->value],
            OrderStatus::Refunded => [
                OrderStatus::Paid->value,
                OrderStatus::Processing->value,
                OrderStatus::Shipped->value,
                OrderStatus::Delivered->value,
            ],
            default => [],
        };
    }
}
