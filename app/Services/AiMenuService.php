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

        $prompt = "You are a professional nutritionist and wellness consultant. Create a short and appealing personalized menu for today based on the user's preferences.\n"
                . "Dietary habits: " . ($preferences['dietary_habits'] ?? 'No specific restrictions') . "\n"
                . "Primary goals: " . ($preferences['goals'] ?? 'Healthy eating') . "\n"
                . "Today's farm-fresh ingredients: " . implode(', ', $availableProducts) . ".\n"
                . "Reply with an ~100-word plan and encourage the user to enjoy a lower-carbon, healthy meal.";

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
        $itemsStr = implode(' and ', $ingredients);
        $habit = $preferences['dietary_habits'] ?? 'Healthy';
        
        return "🌱 [AI Demo] A {$habit} lunch just for you: we picked freshly harvested {$itemsStr}. Lightly sauté with a little olive oil to preserve nutrients and stay aligned with your goals. Lower-carbon and delicious—enjoy!";
    }
}
