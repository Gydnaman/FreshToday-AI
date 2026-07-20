<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\MetricsRecorder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MetricsRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_record_generation_stores_success_timestamp(): void
    {
        MetricsRecorder::recordGeneration('gemini', 'success', 250, 100);

        $this->assertNotNull(Cache::get('ai:last_success:gemini'));
    }

    public function test_record_generation_stores_failure_timestamp(): void
    {
        MetricsRecorder::recordGeneration('gemini', 'failure', 0, 0);

        $this->assertNotNull(Cache::get('ai:last_failure:gemini'));
    }

    public function test_failure_rate_calculation(): void
    {
        // 10 次成功，2 次失败
        for ($i = 0; $i < 10; $i++) {
            MetricsRecorder::recordGeneration('openai', 'success', 200, 100);
        }
        for ($i = 0; $i < 2; $i++) {
            MetricsRecorder::recordGeneration('openai', 'failure', 0, 0);
        }

        $rate = MetricsRecorder::getFailureRate('openai');

        $this->assertEqualsWithDelta(2 / 12, $rate, 0.01);
    }

    public function test_failure_rate_zero_when_no_data(): void
    {
        $this->assertSame(0.0, MetricsRecorder::getFailureRate('deepseek'));
    }
}
