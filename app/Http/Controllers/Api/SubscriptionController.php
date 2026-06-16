<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GuardFailedException;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', 1)->orderBy('price')->get();
        $subs = UserSubscription::with('subscriptionPlan')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'plans' => $plans,
            'user_subscriptions' => $subs,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_plan_id' => 'required|integer|exists:subscription_plans,id',
            'start_date' => 'required|date|after_or_equal:today',
            'auto_renew' => 'boolean',
        ]);

        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        try {
            $sub = $this->service->subscribe(
                user: $request->user(),
                plan: $plan,
                startDate: Carbon::parse($data['start_date']),
                autoRenew: $data['auto_renew'] ?? true,
            );

            return response()->json(['data' => $sub], 201);
        } catch (GuardFailedException $e) {
            $payload = $e->toApiPayload();

            return response()->json(['error' => $payload], $payload['http']);
        }
    }

    public function destroy(Request $request, UserSubscription $subscription): JsonResponse
    {
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json(['error' => ['code' => 'NOT_OWNER', 'message' => '无权操作']], 403);
        }
        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        try {
            $sub = $this->service->cancel($subscription, $data['reason'] ?? 'user_cancel');

            return response()->json(['data' => $sub]);
        } catch (GuardFailedException $e) {
            $payload = $e->toApiPayload();

            return response()->json(['error' => $payload], $payload['http']);
        }
    }
}
