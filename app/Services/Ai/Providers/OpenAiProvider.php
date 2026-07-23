<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\MenuSchema;
use App\Services\Ai\PromptBuilder;
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
            return ['', 0, null];
        }

        $systemPrompt = PromptBuilder::buildSystemPrompt();
        $userPrompt = PromptBuilder::buildUserPrompt($preferences, $products);

        $url = rtrim($this->config['base_url'], '/').'/chat/completions';

        try {
            $response = Http::timeout($this->config['timeout'] ?? 15)
                ->withToken($this->config['key'])
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'model' => $this->config['model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500, // JSON 比纯文本耗 token，放宽到 500
                    'response_format' => MenuSchema::openAiSchema(),
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
                    Log::warning('OpenAiProvider: invalid JSON', [
                        'provider' => $this->name(),
                        'model' => $this->config['model'],
                        'reason' => 'invalid_json',
                    ]);

                    return ['', 0, null];
                }

                Log::warning('OpenAiProvider: empty choices', [
                    'provider' => $this->name(),
                    'model' => $this->config['model'],
                    'reason' => 'empty_choices',
                ]);

                return ['', 0, null];
            }

            Log::warning('OpenAiProvider: non-2xx', [
                'provider' => $this->name(),
                'model' => $this->config['model'],
                'status' => $response->status(),
                'reason' => 'provider_http_error',
            ]);
        } catch (\Throwable $e) {
            Log::warning('OpenAiProvider: exception', [
                'provider' => $this->name(),
                'model' => $this->config['model'],
                'reason' => 'provider_exception',
            ]);
        }

        return ['', 0, null];
    }
}
