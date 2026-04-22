<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiMenuService
{
    /**
     * Generate a personalized daily menu using Gemini API.
     *
     * @param array $preferences ['purpose', 'dietary_habits', 'goals']
     * @param array $availableProducts Array of available product names
     * @return string Generated menu text
     */
    public function generateDailyMenu(array $preferences, array $availableProducts)
    {
        $apiKey = env('GEMINI_API_KEY');

        // Fallback or demo mode if no API key is provided
        if (!$apiKey) {
            return $this->generateFallbackMenu($preferences, $availableProducts);
        }

        $prompt = "你是一位专业的营养师和健康顾问。请为用户生成一份符合其偏好的今日专属菜单（简短且极具吸引力）。\n"
                . "用户的饮食习惯偏好: " . ($preferences['dietary_habits'] ?? '无特定限制') . "\n"
                . "用户的核心目标: " . ($preferences['goals'] ?? '健康饮食') . "\n"
                . "今天农场刚采摘的新鲜食材有: " . implode(', ', $availableProducts) . "。\n"
                . "请直接回复一份 100 字左右的精美食谱搭配，鼓励用户享受这顿零碳足迹的健康大餐。";

        try {
            $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? $this->generateFallbackMenu($preferences, $availableProducts);
            }

            Log::error('Gemini API Error: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Gemini Request Exception: ' . $e->getMessage());
        }

        return $this->generateFallbackMenu($preferences, $availableProducts);
    }

    private function generateFallbackMenu($preferences, $availableProducts)
    {
        $ingredients = array_slice($availableProducts, 0, 2);
        $itemsStr = implode(' 和 ', $ingredients);
        $habit = $preferences['dietary_habits'] ?? '健康';
        
        return "🌱 【AI模拟生成】为您量身定制的{$habit}午餐：我们挑选了刚采摘的 {$itemsStr}，建议用少许橄榄油清炒，既能保留最高营养价值，又能完美契合您的减脂目标！零碳足迹，大自然的原汁原味，请尽情享用吧。";
    }
}
