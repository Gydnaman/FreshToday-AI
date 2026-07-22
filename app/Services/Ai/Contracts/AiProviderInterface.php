<?php

namespace App\Services\Ai\Contracts;

/**
 * AI Provider 适配器接口
 *
 * 任何 LLM Provider（Gemini / OpenAI / DeepSeek / 未来 Anthropic 等）
 * 都必须实现该接口，让 AiMenuService 与具体厂商解耦。
 *
 * 约定：
 *  - generate() 必须捕获一切 \Throwable 异常并降级为返回值 ['', 0]，
 *    **不抛出业务异常**（调用方 AiMenuService 已统一走 fallback 链）
 *  - 返回的 content 永远为 string；空字符串表示"调用失败，请用模板"
 *  - 返回的 tokens_used：成功时为 Provider 报告的 token 数，失败时为 0
 *  - 每次实现需在异常/超时分支 Log::warning 一次，便于监控
 */
interface AiProviderInterface
{
    /**
     * Provider 名（用于 daily_menus.source 字段）
     * 例：'gemini'、'openai'、'deepseek'
     */
    public function name(): string;

    /**
     * 当前 Provider 是否已配置（key 非空等）
     */
    public function isConfigured(): bool;

    /**
     * 生成个性化菜单文本
     *
     * @param  array<string,mixed>  $preferences  来自 UserPreference 的偏好
     * @param  array<int,string>  $products  可售商品名称列表
     * @return array{0:string,1:int,2:?array} [content, tokens_used, json_data]
     *                                        content: 原始文本（JSON 字符串或纯文本）
     *                                        tokens_used: 成功时 Provider 报告的 token 数，失败为 0
     *                                        json_data: 解析后的 JSON 数组（JSON 模式），非 JSON 模式或解析失败为 null
     */
    public function generate(array $preferences, array $products): array;
}
