<?php

namespace App\Http\Controllers;

use App\Services\Ai\Contracts\AiProviderInterface;
use App\Services\Ai\MetricsRecorder;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(private readonly AiProviderInterface $provider) {}

    public function ai(): JsonResponse
    {
        $providerName = $this->provider->name();

        return response()->json([
            'provider' => $providerName,
            'configured' => $this->provider->isConfigured(),
            'last_success_at' => MetricsRecorder::getLastSuccessAt($providerName),
            'last_failure_at' => MetricsRecorder::getLastFailureAt($providerName),
            'failure_rate_1h' => MetricsRecorder::getFailureRate($providerName),
        ]);
    }
}
