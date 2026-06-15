<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;

/**
 * 空 Provider（兜底）
 *
 * 场景：.env 一个 KEY 都没设，或 AI_PROVIDER 指向未实现的厂商
 * 行为：generate() 永远返回空 content；调用方 AiMenuService 走本地模板降级
 *
 * 目的：保证 Service 层永远能拿到一个 Provider 实例，**不抛错**
 *      让"关闭 AI"成为配置层的一行操作（注释掉 KEY）而非代码层
 */
class NullProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'fallback';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function generate(array $preferences, array $products): array
    {
        // 故意返回空 content，让 AiMenuService 走 generateFallbackMenu
        return ['', 0];
    }
}
