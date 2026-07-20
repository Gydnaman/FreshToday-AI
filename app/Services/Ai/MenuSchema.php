<?php

namespace App\Services\Ai;

/**
 * 菜单 JSON Schema 定义
 *
 * 三家 Provider 的 schema 格式略有差异：
 *  - Gemini: responseSchema (OpenAPI 3.0 subset)
 *  - OpenAI: json_schema (JSON Schema draft 2020-12)
 *  - DeepSeek: 仅支持 json_object，schema 靠后端校验
 */
class MenuSchema
{
    /**
     * Gemini responseSchema 格式
     *
     * @see https://ai.google.dev/api/rest/v1beta/GenerationConfig
     */
    public static function geminiSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'greeting' => ['type' => 'string', 'description' => 'Friendly opening line'],
                'meals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['breakfast', 'lunch', 'dinner']],
                            'name' => ['type' => 'string'],
                            'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'description' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'name', 'ingredients', 'description'],
                    ],
                    'minItems' => 3,
                    'maxItems' => 3,
                ],
                'tip' => ['type' => 'string', 'description' => 'Closing nutrition tip'],
            ],
            'required' => ['greeting', 'meals', 'tip'],
        ];
    }

    /**
     * OpenAI Structured Outputs 格式
     *
     * @see https://platform.openai.com/docs/guides/structured-outputs
     */
    public static function openAiSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'daily_menu',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'greeting' => ['type' => 'string'],
                        'meals' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['type' => 'string', 'enum' => ['breakfast', 'lunch', 'dinner']],
                                    'name' => ['type' => 'string'],
                                    'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                                    'description' => ['type' => 'string'],
                                ],
                                'required' => ['type', 'name', 'ingredients', 'description'],
                                'additionalProperties' => false,
                            ],
                            'minItems' => 3,
                            'maxItems' => 3,
                        ],
                        'tip' => ['type' => 'string'],
                    ],
                    'required' => ['greeting', 'meals', 'tip'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * DeepSeek 仅支持 json_object（不强制 schema）
     */
    public static function deepSeekSchema(): array
    {
        return ['type' => 'json_object'];
    }
}
