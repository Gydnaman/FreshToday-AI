<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_circuit_closed_initially(): void
    {
        $breaker = new CircuitBreaker;

        $this->assertFalse($breaker->isOpen('gemini'));
    }

    public function test_circuit_opens_after_threshold_failures(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 3, windowSeconds: 300);

        for ($i = 0; $i < 3; $i++) {
            $breaker->recordFailure('gemini');
        }

        $this->assertTrue($breaker->isOpen('gemini'));
    }

    public function test_circuit_closes_on_success(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 3, windowSeconds: 300);

        for ($i = 0; $i < 3; $i++) {
            $breaker->recordFailure('gemini');
        }
        $this->assertTrue($breaker->isOpen('gemini'));

        $breaker->recordSuccess('gemini');
        $this->assertFalse($breaker->isOpen('gemini'));
    }

    public function test_circuit_resets_after_timeout(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 2, windowSeconds: 1);

        $breaker->recordFailure('gemini');
        $breaker->recordFailure('gemini');
        $this->assertTrue($breaker->isOpen('gemini'));

        sleep(2); // 等待熔断窗口过期

        $this->assertFalse($breaker->isOpen('gemini'), '熔断器应在窗口过期后自动关闭');
    }
}
