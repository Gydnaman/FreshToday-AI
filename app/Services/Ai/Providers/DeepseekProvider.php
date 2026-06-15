<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
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
            return ['', 0];
        }

        $userPrompt = "Create a ~100-word personalized daily menu.\n"
            . "Purpose: " . ($preferences['purpose'] ?? 'Healthy eating') . "\n"
            . "Dietary: " . ($preferences['dietary_habits'] ?? 'No restriction') . "\n"
            . "Goals: " . ($preferences['goals'] ?? 'Wellness') . "\n"
            . "Skill: " . ($preferences['cooking_skill'] ?? 'Beginner') . "\n"
            . "Budget HKD/wk: " . ($preferences['budget_hkd'] ?? 'flexible') . "\n"
            . "Available products: " . implode(', ', $products) . "\n"
            . "Encourage low-carbon, healthy meals. Reply in English.";

        $url = rtrim($this->config['base_url'], '/') . '/chat/completions';

        try {
            $response = Http::timeout($this->config['timeout'] ?? 15)
                ->withToken($this->config['key'])
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'model'       => $this->config['model'],
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a professional nutritionist. Write concise, friendly, low-carbon meal suggestions.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $userPrompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 300,
                    'stream'      => false,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';
                $tokens = (int) ($data['usage']['total_tokens'] ?? 0);
                if ($text !== '') {
                    return [trim($text), $tokens];
                }
                Log::warning('DeepseekProvider: empty choices in response', [
                    'model' => $this->config['model'],
                ]);
                return ['', 0];
            }

            Log::warning('DeepseekProvider: non-2xx response', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('DeepseekProvider: request exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return ['', 0];
    }
}
