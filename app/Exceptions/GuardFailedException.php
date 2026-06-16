<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 守卫条件不满足异常（库存不足 / 支付金额不符 / 订单归属错等）
 */
class GuardFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $guardCode,
        public readonly string $userMessage,
        public readonly array $context = [],
    ) {
        parent::__construct("[{$guardCode}] {$userMessage}");
    }

    public function toApiPayload(): array
    {
        $http = match ($this->guardCode) {
            'GUARD-G0' => 403,
            'GUARD-P1' => 409,
            'GUARD-P3' => 409,
            'GUARD-I1',
            'GUARD-I2',
            'GUARD-I3' => 409,
            default => 422,
        };

        return [
            'http' => $http,
            'code' => $this->guardCode,
            'message' => $this->userMessage,
            'details' => $this->context,
        ];
    }
}
