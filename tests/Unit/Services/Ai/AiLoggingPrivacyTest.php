<?php

namespace Tests\Unit\Services\Ai;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiLoggingPrivacyTest extends TestCase
{
    private const AI_LOGGING_FILES = [
        'app/Services/AiMenuService.php',
        'app/Services/Ai/Providers/GeminiProvider.php',
        'app/Services/Ai/Providers/OpenAiProvider.php',
        'app/Services/Ai/Providers/DeepseekProvider.php',
        'app/Services/Ai/Providers/FailoverProvider.php',
    ];

    #[DataProvider('forbiddenLogPayloadProvider')]
    public function test_ai_logs_do_not_include_sensitive_payloads(
        string $forbiddenSource,
        string $description,
    ): void {
        foreach (self::AI_LOGGING_FILES as $relativePath) {
            $source = file_get_contents(base_path($relativePath));

            $this->assertIsString($source);
            $this->assertStringNotContainsString(
                $forbiddenSource,
                $source,
                "{$relativePath} must not log {$description}",
            );
        }
    }

    public static function forbiddenLogPayloadProvider(): array
    {
        return [
            'model output preview' => ["'content_preview' =>", 'model output previews'],
            'provider text preview' => ["'text' => substr(", 'provider response text'],
            'provider response body' => ["'body' => substr(", 'provider response bodies'],
            'raw exception message' => ["'message' => \$e->getMessage()", 'raw exception messages'],
            'raw exception error' => ["'error' => \$e->getMessage()", 'raw exception messages'],
            'validator exception error' => ["'error' => \$exception->getMessage()", 'raw validator exception messages'],
            'preferences' => ["'preferences' =>", 'user preferences'],
            'prompt' => ["'prompt' =>", 'prompts'],
        ];
    }
}
