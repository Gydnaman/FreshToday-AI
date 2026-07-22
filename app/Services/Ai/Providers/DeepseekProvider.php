<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\MenuSchema;
use App\Services\Ai\PromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DeepSeek Provider（OpenAI 兼容协议）
 *
 * 协议：POST {base_url}/chat/completions
 * 鉴权：Authorization: Bearer {api_key}
 *
 * DeepSeek 文档：https://api-docs.deepseek.com/
 * 模型：deepseek-chat（V3）、deepseek-reasoner（R1）
 *
 * 复用 OpenAI 协议结构，但保持 Provider 独立以便未来 DeepSeek 增加特有参数
 * （response_format、thinking_budget 等）
 */
class DeepseekProvider implements AiProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'deepseek';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['key']);
    }

    public function generate(array $preferences, array $products): array
    {
        if (! $this->isConfigured()) {
            return ['', 0, null];
        }

        $systemPrompt = PromptBuilder::buildSystemPrompt();
        $userPrompt = PromptBuilder::buildUserPrompt($preferences, $products);

        $url = rtrim($this->config['base_url'], '/').'/chat/completions';

        try {
            $http = Http::timeout($this->config['timeout'] ?? 15)
                ->withToken($this->config['key'])
                ->acceptJson()
                ->asJson();

            // 开发环境：Windows + XAMPP 常见 SSL 证书问题，临时禁用验证
            // 生产环境必须移除（或使用正规 CA 证书）
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }

            $response = $http->post($url, [
                'model' => $this->config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => $this->config['max_tokens'] ?? 400,
                'stream' => false,
                // 注释掉 response_format：DeepSeek V4 Flash 对 json_object + 复杂 prompt 组合
                // 可能返回空内容。让模型自由生成，后端 json_decode 失败会走 fallback。
                // 'response_format' => MenuSchema::deepSeekSchema(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';
                $tokens = (int) ($data['usage']['total_tokens'] ?? 0);

                if ($text !== '') {
                    $json = json_decode($text, true);
                    if (is_array($json)) {
                        return [trim($text), $tokens, $json];
                    }
                    Log::warning('DeepseekProvider: invalid JSON', [
                        'text' => substr($text, 0, 200),
                    ]);

                    return ['', 0, null];
                }

                Log::warning('DeepseekProvider: empty choices', [
                    'model' => $this->config['model'],
                ]);

                return ['', 0, null];
            }

            Log::warning('DeepseekProvider: non-2xx', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('DeepseekProvider: exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return ['', 0, null];
    }
}
