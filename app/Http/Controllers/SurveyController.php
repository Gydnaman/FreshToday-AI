<?php

namespace App\Http\Controllers;

/**
 * Web 端问卷入口（/survey）
 *
 * 行为：浏览器访问 /survey 时直接 302 跳转到 SPA 端问卷页面，
 * 由前端通过 /api/survey（Sanctum 鉴权）提交数据。早期版本的"写 session"
 * 占位逻辑已废弃 —— session 在 token 鉴权 API 模式下不再可用。
 */
class SurveyController extends Controller
{
    public function create()
    {
        // SPA 重定向（前端路由）
        return redirect()->away('/dashboard#survey');
    }

    public function store()
    {
        // POST /survey 落到 web 路由已无业务意义，统一拒收并提示走 API
        return response()->view('errors.410', [], 410);
    }
}
