<?php

namespace App\Enums;

/**
 * 守卫代码枚举（Guard 失败原因 SSOT）
 *
 * 单一真值源：所有 GuardFailedException 必须用 enum case，不允许散落字符串。
 * 短名（G0/P1/I1/...）→ value 保留 'GUARD-G0' 兼容 API 输出。
 * httpStatus() 在 enum 内（不外建映射表）—— GuardFailedException::toApiPayload 委托这里。
 *
 * 详见 ADR-007（评审报告落地）附录 B 守卫规范。
 */
enum GuardCode: string
{
    case G0 = 'GUARD-G0';        // 订单归属（非 owner 且非 admin）
    case G1 = 'GUARD-G1';        // 状态机非法转移（兼容 G1 命名）
    case P1 = 'GUARD-P1';        // 支付单缺失 / 金额不符 / 订单状态不允许
    case P2 = 'GUARD-P2';        // 退款：状态不允许
    case P3 = 'GUARD-P3';        // 币种不一致（仅支持 HKD）
    case P4 = 'GUARD-P4';        // 幂等：succeeded 是 payment 终态不可覆盖
    case I1 = 'GUARD-I1';        // 库存：数量非法 / 库存不足
    case I2 = 'GUARD-I2';        // 库存：行锁失败
    case I3 = 'GUARD-I3';        // 库存：并发扣减冲突
    case Coupon = 'GUARD-COUPON'; // 优惠券：无效 / 不满足最低金额
    case Sub = 'GUARD-SUB';       // 订阅：已有活跃 / 重复取消
    case Ai = 'GUARD-AI';         // AI：用户未填写问卷偏好
    case AiRate = 'GUARD-AI-RATE'; // AI：每日重新生成次数超限

    /** HTTP 状态码（move-out 自 GuardFailedException::toApiPayload） */
    public function httpStatus(): int
    {
        return match ($this) {
            self::G0 => 403,
            self::P1, self::P2, self::P3, self::P4,
            self::I1, self::I2, self::I3,
            self::Sub => 409,
            self::Coupon, self::Ai, self::AiRate => 422,
        };
    }

    /** 人类可读默认消息（构造时可覆盖） */
    public function defaultMessage(): string
    {
        return match ($this) {
            self::G0 => '无权操作此订单',
            self::G1 => '订单状态非法转移',
            self::P1 => '订单未找到匹配的成功支付单',
            self::P2 => '订单当前状态不允许退款',
            self::P3 => '币种不一致（仅支持 HKD）',
            self::P4 => '支付单已终态（succeeded/refunded），不可覆盖',
            self::I1 => '库存不足或数量非法',
            self::I2 => '库存行锁失败',
            self::I3 => '库存并发扣减冲突',
            self::Coupon => '优惠券无效或不满足条件',
            self::Sub => '订阅状态不允许该操作',
            self::Ai => '用户未填写问卷偏好，无法生成菜单',
            self::AiRate => 'AI 菜单每日重新生成次数超限',
        };
    }

    /** 工厂方法（接收短名 G0/P1/I1 等）以支持历史 API 入参 */
    public static function fromShortCode(string $shortOrLong): self
    {
        $normalized = strtoupper(trim($shortOrLong));
        $long = str_starts_with($normalized, 'GUARD-') ? $normalized : "GUARD-{$normalized}";

        foreach (self::cases() as $case) {
            if ($case->value === $long) {
                return $case;
            }
        }

        throw new \ValueError("Unknown GuardCode: {$shortOrLong}");
    }
}
