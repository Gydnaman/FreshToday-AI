<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * AI Provider 熔断器
 *
 * 职责：某 Provider 连续失败达到阈值时，临时熔断（跳过调用），避免雪崩。
 *
 * 状态机：
 *  - Closed（正常）：失败计数 < 阈值
 *  - Open（熔断）：失败计数 >= 阈值，持续 windowSeconds 秒
 *  - Half-Open（试探）：窗口过期后下一次调用允许通过，成功则 Closed，失败则重新 Open
 *
 * 存储：Redis（Cache facade）
 *  - circuit:{provider}:failures  失败计数（带 TTL）
 *  - circuit:{provider}:opened_at  熔断开启时间戳
 */
class CircuitBreaker
{
    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $windowSeconds = 600, // 10 分钟
    ) {}

    public function isOpen(string $provider): bool
    {
        $failures = (int) Cache::get("circuit:{$provider}:failures", 0);

        if ($failures < $this->failureThreshold) {
            return false;
        }

        $openedAt = Cache::get("circuit:{$provider}:opened_at");
        if (! $openedAt) {
            // 达到阈值但未记录 opened_at，立即熔断
            Cache::put("circuit:{$provider}:opened_at", now()->timestamp, $this->windowSeconds);

            return true;
        }

        // 检查是否已过熔断窗口
        if (now()->timestamp - $openedAt > $this->windowSeconds) {
            $this->reset($provider);

            return false;
        }

        return true;
    }

    public function recordFailure(string $provider): void
    {
        $key = "circuit:{$provider}:failures";
        // 读改写：避免 Cache::increment 在某些 store（如 database）对缺失 key 返回 false 导致计数器写不进去
        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, $this->windowSeconds);

        if ($failures >= $this->failureThreshold) {
            Cache::put("circuit:{$provider}:opened_at", now()->timestamp, $this->windowSeconds);
        }
    }

    public function recordSuccess(string $provider): void
    {
        $this->reset($provider);
    }

    private function reset(string $provider): void
    {
        Cache::forget("circuit:{$provider}:failures");
        Cache::forget("circuit:{$provider}:opened_at");
    }
}
