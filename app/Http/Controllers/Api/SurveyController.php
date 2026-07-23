<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateDailyMenuJob;
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

    public function store(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        // Web 表单多选 checkbox 提交 goals[] 数组；API 客户端提交字符串 —— 统一规整为字符串
        if (is_array($request->input('goals'))) {
            $request->merge(['goals' => implode('、', $request->input('goals'))]);
        }

        $data = $request->validate([
            'usage_purpose' => 'required|string|max:100',
            'dietary_habits' => 'required|string|max:100',
            'goals' => 'required|string|max:100',
            'allergies' => 'nullable|array',
            'allergies.*' => 'string',
            'household_size' => 'nullable|integer|min:1|max:20',
            'cooking_skill' => 'nullable|in:Beginner,Intermediate,Advanced',
            'budget_hkd' => 'nullable|numeric|min:0|max:99999.99',
        ]);

        $pref = UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        // 异步触发 AI 菜单生成（避免阻塞）
        GenerateDailyMenuJob::dispatch($request->user()->id);

        // 原生表单提交（非 AJAX）：重定向回首页，避免浏览器显示裸 JSON
        if (! $request->expectsJson()) {
            return redirect('/');
        }

        return response()->json(['data' => $pref]);
    }
}
