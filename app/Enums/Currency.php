<?php

namespace App\Enums;

/**
 * 货币枚举（订单/支付币种 SSOT）
 *
 * 仅 HKD 在 checkout 流程被接受；USD/CNY 留作未来扩展。
 * 收口 3 处散落字面量：OrderService:233 / PaymentService::createIntent / 任何未来调用。
 *
 * 详见 ADR-007 P1-6。
 */
enum Currency: string
{
    case HKD = 'HKD';
    case USD = 'USD';
    case CNY = 'CNY';

    /** 是否在 checkout 流程被接受（GreenBite 现阶段仅 HK 本地业务） */
    public function isAcceptedForCheckout(): bool
    {
        return $this === self::HKD;
    }
}
