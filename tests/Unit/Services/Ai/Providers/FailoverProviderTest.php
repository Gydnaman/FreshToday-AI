<?php

namespace Tests\Unit\Services\Ai\Providers;

use App\Services\Ai\CircuitBreaker;
use App\Services\Ai\Providers\FailoverProvider;
use App\Services\Ai\Providers\NullProvider;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FailoverProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_failover_returns_first_successful_provider(): void
    {
        $primary = new class extends NullProvider
        {
            public function name(): string
            {
                return 'primary';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function generate(array $p, array $pr): array
            {
                return ['', 0, null];
            } // 失败
        };

        $secondary = new class extends NullProvider
        {
            public function name(): string
            {
                return 'secondary';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function generate(array $p, array $pr): array
            {
                return ['menu', 100, null];
            } // 成功
        };

        $failover = new FailoverProvider([$primary, $secondary], new CircuitBreaker);
        [$content, $tokens] = $failover->generate([], []);

        $this->assertSame('menu', $content);
        $this->assertSame(100, $tokens);
    }

    public function test_failover_skips_circuit_open_provider(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 1, windowSeconds: 600);

        $primary = new class extends NullProvider
        {
            public function name(): string
            {
                return 'primary';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function generate(array $p, array $pr): array
            {
                return ['primary_menu', 50, null];
            }
        };

        // 手动熔断 primary
        $breaker->recordFailure('primary');

        $secondary = new class extends NullProvider
        {
            public function name(): string
            {
                return 'secondary';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function generate(array $p, array $pr): array
            {
                return ['secondary_menu', 100, null];
            }
        };

        $failover = new FailoverProvider([$primary, $secondary], $breaker);
        [$content] = $failover->generate([], []);

        $this->assertSame('secondary_menu', $content, '应跳过熔断的 primary');
    }

    public function test_failover_returns_empty_when_all_fail(): void
    {
        $failover = new FailoverProvider([new NullProvider, new NullProvider]);
        [$content, $tokens] = $failover->generate([], []);

        $this->assertSame('', $content);
        $this->assertSame(0, $tokens);
    }
}
