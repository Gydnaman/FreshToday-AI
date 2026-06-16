<?php

namespace App\Exceptions;

use App\Enums\GuardCode;
use RuntimeException;

/**
 * 守卫条件不满足异常（库存不足 / 支付金额不符 / 订单归属错等）
 *
 * SSOT：所有 throw 必须用 GuardCode enum case，禁止散落字符串。
 * 兼容：构造器仍接受 string（自动解析为 enum），方便迁移期渐进式替换。
 *
 * 字段名设计：避免与父类 Exception::$code 冲突（PHP 内置 readonly 不可重写），
 * 改用 $guardCode 存储 GuardCode enum。
 */
class GuardFailedException extends RuntimeException
{
    public readonly GuardCode $guardCode;

    public readonly string $userMessage;

    /** @var array<string, mixed> */
    public readonly array $context;

    /**
     * @param  GuardCode|string  $code  enum case 或历史短名/全名（兼容）
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        GuardCode|string $code,
        ?string $userMessage = null,
        array $context = [],
    ) {
        $this->guardCode = $code instanceof GuardCode ? $code : GuardCode::fromShortCode($code);
        $this->userMessage = $userMessage ?? $this->guardCode->defaultMessage();
        $this->context = $context;

        parent::__construct("[{$this->guardCode->value}] {$this->userMessage}");
    }

    public function toApiPayload(): array
    {
        return [
            'http' => $this->guardCode->httpStatus(),
            'code' => $this->guardCode->value,
            'message' => $this->userMessage,
            'details' => $this->context,
        ];
    }
}
