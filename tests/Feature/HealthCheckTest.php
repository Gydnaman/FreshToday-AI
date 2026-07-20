<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_ai_endpoint_returns_provider_status(): void
    {
        Cache::flush();

        $response = $this->getJson('/api/health/ai');

        $response->assertOk()
            ->assertJsonStructure([
                'provider',
                'configured',
                'last_success_at',
                'last_failure_at',
                'failure_rate_1h',
            ]);
    }
}
