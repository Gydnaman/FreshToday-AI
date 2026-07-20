<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\CircuitBreaker;
use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * 多 Provider 灾备装饰器
 *
 * 职责：按优先级顺序尝试多个 Provider，任一成功即返回；全部失败返回空。
 *       配合 CircuitBreaker 跳过熔断的 Provider。
 *
 * 成功定义：Provider 返回 content 非空即视为成功（不校验 json_data）。
 *   JSON 解析失败导致的 json_data=null 由 AiMenuService 的降级路径处理，
 *   failover 层不感知 JSON 解析层。
 *
 * 使用：
 *   $failover = new FailoverProvider([
 *       new DeepseekProvider($config),
 *       new OpenAiProvider($config),
 *       new GeminiProvider($config),
 *   ], new CircuitBreaker);
 */
class FailoverProvider implements AiProviderInterface
{
    /**
     * @param  array<int,AiProviderInterface>  $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly CircuitBreaker $breaker = new CircuitBreaker,
    ) {}

    public function name(): string
    {
        // 返回当前生效的 Provider 名（第一个非熔断的）
        foreach ($this->providers as $provider) {
            if (! $this->breaker->isOpen($provider->name())) {
                return $provider->name();
            }
        }

        return 'failover'; // 全部熔断
    }

    public function isConfigured(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                return true;
            }
        }

        return false;
    }

    public function generate(array $preferences, array $products): array
    {
        foreach ($this->providers as $provider) {
            $name = $provider->name();

            // 跳过熔断的 Provider
            if ($this->breaker->isOpen($name)) {
                Log::info("FailoverProvider: skip {$name} (circuit open)");

                continue;
            }

            // 跳过未配置的 Provider
            if (! $provider->isConfigured()) {
                continue;
            }

            try {
                [$content, $tokens, $json] = $provider->generate($preferences, $products);

                if ($content !== '') {
                    $this->breaker->recordSuccess($name);

                    return [$content, $tokens, $json];
                }

                // Provider 返回空 = 失败
                $this->breaker->recordFailure($name);
            } catch (\Throwable $e) {
                Log::warning("FailoverProvider: {$name} exception", ['error' => $e->getMessage()]);
                $this->breaker->recordFailure($name);
            }
        }

        // 全部失败
        return ['', 0, null];
    }
}
