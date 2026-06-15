<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $pref = $request->user()->userPreferences;
        return response()->json(['data' => $pref]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'usage_purpose'  => 'required|string|max:100',
            'dietary_habits' => 'required|string|max:100',
            'goals'          => 'required|string|max:100',
            'allergies'      => 'nullable|array',
            'allergies.*'    => 'string',
            'household_size' => 'nullable|integer|min:1|max:20',
            'cooking_skill'  => 'nullable|in:Beginner,Intermediate,Advanced',
            'budget_hkd'     => 'nullable|numeric|min:0|max:99999.99',
        ]);

        $pref = UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        // 异步触发 AI 菜单生成（避免阻塞）
        \App\Jobs\GenerateDailyMenuJob::dispatch($request->user()->id);

        return response()->json(['data' => $pref]);
    }
}
