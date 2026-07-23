<?php

namespace Tests\Unit\Services\Ai\Providers;

use App\Services\Ai\MenuSchema;
use App\Services\Ai\Providers\DeepseekProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JsonOutputTest extends TestCase
{
    public function test_menu_schema_has_required_fields(): void
    {
        $gemini = MenuSchema::geminiSchema();
        $this->assertArrayHasKey('type', $gemini);
        $this->assertSame('object', $gemini['type']);
        $this->assertArrayHasKey('properties', $gemini);
        $this->assertArrayHasKey('greeting', $gemini['properties']);
        $this->assertArrayHasKey('meals', $gemini['properties']);
        $this->assertArrayHasKey('tip', $gemini['properties']);

        $openai = MenuSchema::openAiSchema();
        $this->assertArrayHasKey('type', $openai);
        $this->assertSame('json_schema', $openai['type']);
        $this->assertArrayHasKey('json_schema', $openai);
    }

    public function test_gemini_provider_requests_json_output(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'greeting' => 'Hi',
                        'meals' => [
                            ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['Tomato'], 'description' => 'Y'],
                            ['type' => 'lunch', 'name' => 'X', 'ingredients' => ['Spinach'], 'description' => 'Y'],
                            ['type' => 'dinner', 'name' => 'X', 'ingredients' => ['Salmon'], 'description' => 'Y'],
                        ],
                        'tip' => 'Tip',
                    ])]]],
                ]],
                'usageMetadata' => ['totalTokenCount' => 100],
            ], 200),
        ]);

        $provider = new GeminiProvider([
            'key' => 'fake',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'model' => 'gemini-2.5-flash',
            'timeout' => 8,
        ]);

        [$content, $tokens, $json] = $provider->generate(['purpose' => 'X'], ['Tomato', 'Spinach', 'Salmon']);

        $this->assertNotEmpty($content);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('meals', $json);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['generationConfig']['responseMimeType'])
                && $body['generationConfig']['responseMimeType'] === 'application/json'
                && isset($body['generationConfig']['responseSchema']);
        });
    }

    public function test_openai_provider_requests_json_output(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'greeting' => 'Hi',
                        'meals' => [
                            ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['Tomato'], 'description' => 'Y'],
                            ['type' => 'lunch', 'name' => 'X', 'ingredients' => ['Spinach'], 'description' => 'Y'],
                            ['type' => 'dinner', 'name' => 'X', 'ingredients' => ['Salmon'], 'description' => 'Y'],
                        ],
                        'tip' => 'Tip',
                    ])],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $provider = new OpenAiProvider([
            'key' => 'fake',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'timeout' => 15,
        ]);

        [$content, $tokens, $json] = $provider->generate(['purpose' => 'X'], ['Tomato', 'Spinach', 'Salmon']);

        $this->assertIsArray($json);
        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            return isset($body['response_format']['type'])
                && $body['response_format']['type'] === 'json_schema'
                && ($body['response_format']['json_schema']['name'] ?? '') === 'daily_menu'
                && ($body['response_format']['json_schema']['strict'] ?? false) === true
                && ($body['response_format']['json_schema']['schema']['additionalProperties'] ?? true) === false;
        });
    }

    public function test_deepseek_provider_parses_json_from_content(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'greeting' => 'Hi',
                        'meals' => [
                            ['type' => 'breakfast', 'name' => 'X', 'ingredients' => ['Tomato'], 'description' => 'Y'],
                            ['type' => 'lunch', 'name' => 'X', 'ingredients' => ['Spinach'], 'description' => 'Y'],
                            ['type' => 'dinner', 'name' => 'X', 'ingredients' => ['Salmon'], 'description' => 'Y'],
                        ],
                        'tip' => 'Tip',
                    ])],
                ]],
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $provider = new DeepseekProvider([
            'key' => 'fake',
            'base_url' => 'https://api.deepseek.com/v1',
            'model' => 'deepseek-chat',
            'timeout' => 15,
        ]);

        [$content, $tokens, $json] = $provider->generate(['purpose' => 'X'], ['Tomato', 'Spinach', 'Salmon']);

        // DeepSeek 不用 response_format（V4 Flash 返回空），但 prompt 要求 JSON 输出
        // Provider 内部 json_decode 解析 content
        $this->assertIsArray($json);
        $this->assertArrayHasKey('meals', $json);
        $this->assertEquals(100, $tokens);
    }

    public function test_deepseek_provider_uses_configured_output_budget(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => '{}'],
                ]],
                'usage' => ['total_tokens' => 1],
            ], 200),
        ]);

        $provider = new DeepseekProvider([
            'key' => 'fake',
            'base_url' => 'https://api.deepseek.com/v1',
            'model' => 'deepseek-chat',
            'max_tokens' => 400,
            'timeout' => 15,
        ]);

        $provider->generate(['purpose' => 'X'], ['Tomato']);

        Http::assertSent(fn ($request) => $request['model'] === 'deepseek-chat'
            && $request['max_tokens'] === 400
            && count($request['messages']) === 2
        );
    }
}
