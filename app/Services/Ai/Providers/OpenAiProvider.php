<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Provider（Chat Completions 协议）
 *
 * 协议：POST {base_url}/chat/completions
 * 鉴权：Authorization: Bearer {api_key}
 *
 * 兼容 OpenAI 全系（gpt-4o / gpt-4o-mini / gpt-4-turbo / gpt-3.5-turbo）
 * 及任何 OpenAI 兼容网关（Azure OpenAI / OpenRouter / vLLM 自部署等）
 */
class OpenAiProvider implements AiProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'openai';
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
            . "Budget HKD/wk: " . ($preferences['budget_habits'] ?? 'flexible') . "\n"
            . "Available products: " . implode(', ', $products) . "\n"
            . "Encourage low-carbon, healthy meals.";

        $url = rtrim($this->config['base_url'], '/') . '/chat/completions';

        try {
            $response = Http::timeout($this->config['timeout'] ?? 15)
                ->withToken($this->config['key'])
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'model'    => $this->config['model'],
                    'messages' => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a professional nutritionist who writes concise, friendly meal suggestions focused on low-carbon and healthy eating.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $userPrompt,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens'  => 300,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';
                $tokens = (int) ($data['usage']['total_tokens'] ?? 0);
                if ($text !== '') {
                    return [trim($text), $tokens];
                }
                Log::warning('OpenAiProvider: empty choices in response', [
                    'model' => $this->config['model'],
                ]);
                return ['', 0];
            }

            Log::warning('OpenAiProvider: non-2xx response', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('OpenAiProvider: request exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return ['', 0];
    }
}
