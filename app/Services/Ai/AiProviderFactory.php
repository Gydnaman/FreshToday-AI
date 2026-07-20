<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\Providers\DeepseekProvider;
use App\Services\Ai\Providers\FailoverProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\NullProvider;
use App\Services\Ai\Providers\OpenAiProvider;

/**
 * AI Provider 工厂
 *
 * 解析规则（按优先级）：
 *   1. config('ai.default') 显式指定 → 强制使用该 Provider
 *   2. 未指定 → 按 config('ai.auto_detect_order') 顺序查找第一个 key 非空的
 *   3. 全部为空 → 返回 NullProvider（永远走 fallback 模板，AI 等同关闭）
 *
 * 该工厂在 ServiceProvider 中以单例方式 bind：
 *   $this->app->singleton(AiProviderInterface::class, function () {
 *       return AiProviderFactory::make();
 *   });
 */
class AiProviderFactory
{
    public static function make(): AiProviderInterface
    {
        $config = config('ai');

        // Failover 模式：启用后按 failover_order 顺序尝试多个 Provider
        if ($config['failover_enabled'] ?? false) {
            return self::buildFailover($config);
        }

        // 1. 显式指定（如果该 Provider 不可用，回退到 auto_detect，不抛错）
        $explicit = $config['default'] ?? null;
        if ($explicit) {
            $provider = self::build($explicit);
            if ($provider !== null) {
                return $provider;
            }
        }

        // 2. 按 auto_detect_order 探测
        foreach ($config['auto_detect_order'] ?? [] as $name) {
            $provider = self::build($name);
            if ($provider !== null) {
                return $provider;
            }
        }

        // 3. 全部不可用 → Null（永不抛错，UI 走 fallback 模板）
        return new NullProvider;
    }

    /**
     * 构建 Failover 模式：多 Provider 灾备 + Circuit Breaker 熔断
     */
    private static function buildFailover(array $config): AiProviderInterface
    {
        $providers = [];
        foreach ($config['failover_order'] ?? ['deepseek', 'openai', 'gemini'] as $name) {
            $provider = self::build($name);
            if ($provider !== null) {
                $providers[] = $provider;
            }
        }

        if (empty($providers)) {
            return new NullProvider;
        }

        return new FailoverProvider($providers, new CircuitBreaker(
            failureThreshold: $config['circuit_breaker']['failure_threshold'] ?? 5,
            windowSeconds: $config['circuit_breaker']['window_seconds'] ?? 600,
        ));
    }

    /**
     * 构建指定 name 的 Provider；返回 null 表示该 Provider 不可用（缺 key 等）
     *
     * Key 来源（按优先级）：
     *  1. config('ai.providers.{name}.key') —— 标准路径（运行时配置）
     *  2. env('XXX_API_KEY') —— 兜底（兼容测试 putenv 注入与 config-cache 场景）
     */
    public static function build(string $name): ?AiProviderInterface
    {
        $cfg = config("ai.providers.{$name}");
        if (! $cfg) {
            return null;
        }

        // 兜底：config 没读到（config cache 固化 / 测试 setUp 未注入新 key）时
        // 直接读 env()，保持向后兼容且支持单测 putenv
        $envKey = match ($name) {
            'gemini' => 'GEMINI_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            'deepseek' => 'DEEPSEEK_API_KEY',
            default => null,
        };
        if (empty($cfg['key']) && $envKey) {
            $envVal = env($envKey);
            if ($envVal) {
                $cfg['key'] = $envVal;
            }
        }

        if (empty($cfg['key'])) {
            return null;
        }

        return match ($name) {
            'gemini' => new GeminiProvider($cfg),
            'openai' => new OpenAiProvider($cfg),
            'deepseek' => new DeepseekProvider($cfg),
            default => null,
        };
    }
}
