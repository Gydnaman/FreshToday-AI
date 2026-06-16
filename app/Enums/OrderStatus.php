<?php

namespace App\Enums;

/**
 * 订单状态枚举（7 态）
 *
 * 单一真相源（SSOT）：docs/bmad/order-state-machine.md 附录 A
 * 与 er-diagram.md §2.6 orders.status VARCHAR(32) 字段枚举一致
 * 与 api-contract.md §A.2 合法转移对照表一致
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待支付',
            self::Paid => '已支付',
            self::Processing => '处理中',
            self::Shipped => '已发货',
            self::Delivered => '已签收',
            self::Cancelled => '已取消',
            self::Refunded => '已退款',
        };
    }

    /** 终态：状态机不再前进 */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Refunded], true)
            || $this === self::Delivered;
    }

    /** 可被支付的来源状态 */
    public function canBePaid(): bool
    {
        return $this === self::Pending;
    }

    /** 可被取消的来源状态（仅待支付可用户自取消） */
    public function canBeCancelled(): bool
    {
        return $this === self::Pending;
    }

    /** 可被退款的来源状态 */
    public function canBeRefunded(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Processing,
            self::Shipped,
            self::Delivered,
        ], true);
    }

    /** 合法状态值集合（用于 ER CHECK 约束 / API 校验） */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
