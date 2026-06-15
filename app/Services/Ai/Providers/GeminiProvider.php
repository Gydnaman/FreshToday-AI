<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProviderInterface;
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
            return ['', 0];
        }

        $prompt = "You are a professional nutritionist. Create a ~100-word personalized daily menu.\n"
            . "Purpose: " . ($preferences['purpose'] ?? 'Healthy eating') . "\n"
            . "Dietary: " . ($preferences['dietary_habits'] ?? 'No restriction') . "\n"
            . "Goals: " . ($preferences['goals'] ?? 'Wellness') . "\n"
            . "Skill: " . ($preferences['cooking_skill'] ?? 'Beginner') . "\n"
            . "Budget HKD/wk: " . ($preferences['budget_hkd'] ?? 'flexible') . "\n"
            . "Available: " . implode(', ', $products) . "\n"
            . "Encourage low-carbon, healthy meals.";

        $url = rtrim($this->config['base_url'], '/')
            . '/models/' . $this->config['model']
            . ':generateContent?key=' . $this->config['key'];

        try {
            $response = Http::timeout($this->config['timeout'] ?? 8)->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $tokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);
                if ($text !== '') {
                    return [$text, $tokens];
                }
                Log::warning('GeminiProvider: empty candidates in response', [
                    'model' => $this->config['model'],
                ]);
                return ['', 0];
            }

            Log::warning('GeminiProvider: non-2xx response', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('GeminiProvider: request exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return ['', 0];
    }
}
