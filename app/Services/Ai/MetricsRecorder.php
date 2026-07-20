<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * AI 指标记录器
 *
 * 职责：记录每次生成的成功/失败/延迟/tokens，供健康检查和告警用。
 *
 * 存储：Redis（Cache facade）
 *  - ai:last_success:{provider}  最后成功时间戳
 *  - ai:last_failure:{provider}  最后失败时间戳
 *  - ai:metrics:{provider}:success  成功计数（1h 滑动窗口）
 *  - ai:metrics:{provider}:failure  失败计数
 *
 * 失败率窗口：固定 1h（TTL_SECONDS）。窗口从首次记录开始计算，
 * 之后 increment 不续期——这是严格的"最近 1h"语义。
 *
 * 生产建议：高峰期可换成 Prometheus pushgateway，这里先用 Cache 实现简单版。
 */
class MetricsRecorder
{
    private const TTL_SECONDS = 3600; // 1h 滑动窗口

    public static function recordGeneration(string $provider, string $status, int $latencyMs, int $tokens): void
    {
        // latencyMs 暂未使用，后续加 Stopwatch 后接入 latency 统计
        $now = now()->toIso8601String();

        if ($status === 'success') {
            Cache::put("ai:last_success:{$provider}", $now, self::TTL_SECONDS * 24);
            self::incrementWithTtl("ai:metrics:{$provider}:success");
        } else {
            Cache::put("ai:last_failure:{$provider}", $now, self::TTL_SECONDS * 24);
            self::incrementWithTtl("ai:metrics:{$provider}:failure");
        }
    }

    /**
     * 失败率（固定 1h 窗口，从首次记录开始计算）
     */
    public static function getFailureRate(string $provider): float
    {
        $success = (int) Cache::get("ai:metrics:{$provider}:success", 0);
        $failure = (int) Cache::get("ai:metrics:{$provider}:failure", 0);
        $total = $success + $failure;

        return $total > 0 ? $failure / $total : 0.0;
    }

    public static function getLastSuccessAt(string $provider): ?string
    {
        return Cache::get("ai:last_success:{$provider}");
    }

    public static function getLastFailureAt(string $provider): ?string
    {
        return Cache::get("ai:last_failure:{$provider}");
    }

    /**
     * 计数器 +1，首次创建时带 TTL
     *
     * Cache::add 只在 key 不存在时设置（原子）且带 TTL；
     * increment 在已存在 key 上只增不重置 TTL。
     * 组合效果：TTL 从第一次写入开始算 1h，不被后续 increment 干扰。
     */
    private static function incrementWithTtl(string $key): void
    {
        Cache::add($key, 0, self::TTL_SECONDS); // 首次创建（已存在则 no-op）
        Cache::increment($key);
    }
}
