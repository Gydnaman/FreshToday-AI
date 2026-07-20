<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\MenuSchema;
use App\Services\Ai\PromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini Provider
 *
 * 协议：generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
 * 鉴权：query string ?key={api_key}
 *
 * 兼容：gemini-2.5-flash、gemini-2.0-flash、gemini-1.5-pro 等所有 v1beta 模型
 */
class GeminiProvider implements AiProviderInterface
{
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'gemini';
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

        // Gemini v1beta 无独立 system role，把 system + user 合并到 contents
        $combinedPrompt = $systemPrompt."\n\n".$userPrompt;

        $url = rtrim($this->config['base_url'], '/')
            .'/models/'.$this->config['model']
            .':generateContent?key='.$this->config['key'];

        try {
            $response = Http::timeout($this->config['timeout'] ?? 8)->post($url, [
                'contents' => [['parts' => [['text' => $combinedPrompt]]]],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => MenuSchema::geminiSchema(),
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $tokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);

                if ($text !== '') {
                    $json = json_decode($text, true);
                    if (is_array($json)) {
                        return [$text, $tokens, $json];
                    }
                    Log::warning('GeminiProvider: invalid JSON in response', [
                        'text' => substr($text, 0, 200),
                    ]);

                    return ['', 0, null];
                }

                Log::warning('GeminiProvider: empty candidates', [
                    'model' => $this->config['model'],
                ]);

                return ['', 0, null];
            }

            Log::warning('GeminiProvider: non-2xx response', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GeminiProvider: request exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return ['', 0, null];
    }
}
