<?php

/**
 * GreenBite AI 服务配置（Sprint 2 引入多 Provider）
 *
 * 用法：
 *   - 仅设 .env 中任一 Provider 的 API KEY，系统自动选用（优先级：deepseek > openai > gemini）
 *   - 也可显式指定 AI_PROVIDER 强制使用某个 Provider
 *   - 一个 KEY 都不设 → 走 NullProvider → 直接走本地模板降级
 *
 * 切换示例（.env）：
 *   # 用 DeepSeek（推荐：HK 出口稳定、价格低、OpenAI 兼容）
 *   DEEPSEEK_API_KEY=sk-...
 *   # 用 OpenAI
 *   OPENAI_API_KEY=sk-...
 *   # 用 Gemini（默认）
 *   GEMINI_API_KEY=AIzaSy...
 *
 * 模型默认：
 *   - gemini-2.5-flash     （Google Gemini 2.5 Flash）
 *   - gpt-4o-mini          （OpenAI GPT-4o mini）
 *   - deepseek-chat        （DeepSeek V3，对话模型）
 */

return [

    /*
     * 显式指定 Provider；留空则按 key 自动探测
     * 可选：gemini | openai | deepseek
     */
    'default' => env('AI_PROVIDER'),

    /*
     * 通用：HTTP 调用超时（秒）。Gemini 经验值 8s；OpenAI/DeepSeek 可放宽至 15s
     */
    'timeout' => (int) env('AI_TIMEOUT', 12),

    /*
     * Provider 列表
     * - key：留空表示该 Provider 不可用，Factory 会跳过
     * - model：可通过 env 覆盖（AI_GEMINI_MODEL / AI_OPENAI_MODEL / AI_DEEPSEEK_MODEL）
     */
    'providers' => [

        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => env('AI_GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash')),
            'timeout' => (int) env('AI_TIMEOUT', 8),
        ],

        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
            'timeout' => (int) env('AI_TIMEOUT', 15),
        ],

        'deepseek' => [
            'key' => env('DEEPSEEK_API_KEY'),
            // DeepSeek 提供 OpenAI 兼容协议
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
            'model' => env('AI_DEEPSEEK_MODEL', 'deepseek-chat'),
            'max_tokens' => (int) env('AI_DEEPSEEK_MAX_TOKENS', 400),
            'timeout' => (int) env('AI_TIMEOUT', 15),
        ],
    ],

    /*
     * 探测顺序：当 AI_PROVIDER 留空时按此顺序查找第一个有 key 的 Provider
     */
    'auto_detect_order' => ['deepseek', 'openai', 'gemini'],

    /*
     * Failover 模式：启用后按 failover_order 顺序尝试多个 Provider
     * 配合 CircuitBreaker 跳过熔断的 Provider
     */
    'failover_enabled' => (bool) env('AI_FAILOVER_ENABLED', false),

    'failover_order' => ['deepseek', 'openai', 'gemini'],

    'circuit_breaker' => [
        'failure_threshold' => (int) env('AI_CB_FAILURE_THRESHOLD', 5),
        'window_seconds' => (int) env('AI_CB_WINDOW_SECONDS', 600),
    ],
];
