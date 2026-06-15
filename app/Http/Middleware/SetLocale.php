<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetLocale 中间件
 *
 * 解析请求 locale 优先级：
 *   1. ?lang=xx 查询参数（最高优先级，方便外部链接直接切换）
 *   2. cookie 'gb_locale'（用户上次切换持久化）
 *   3. Accept-Language header（浏览器首选语言）
 *   4. config('app.locale')（系统兜底）
 *
 * 仅白名单内的 locale 才会被接受，非法值回退到 'zh-HK'。
 * 当用户通过 ?lang= 切换时，写 cookie 持久 365 天。
 *
 * 对应 SSOT：docs/i18n/PLAN-i18n.md §2.2
 */
class SetLocale
{
    /**
     * 支持的语言白名单
     */
    private const SUPPORTED = ['zh-HK', 'en', 'zh-CN'];

    /**
     * 默认语言（白名单非法值兜底）
     */
    private const DEFAULT = 'zh-HK';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        $response = $next($request);

        // 仅在 URL 显式带 ?lang= 时回写 cookie（让切换可持久化）
        if ($request->query('lang') !== null) {
            cookie()->queue(cookie('gb_locale', $locale, 60 * 24 * 365, '/', null, false, false));
        }

        // 暴露给响应头，便于 CDN / 调试
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    /**
     * 解析请求 locale（按优先级四次回退 + 白名单校验）
     */
    private function resolveLocale(Request $request): string
    {
        $candidates = [
            $request->query('lang'),
            $request->cookie('gb_locale'),
            $this->parseAcceptLanguage($request->header('Accept-Language')),
            config('app.locale'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, self::SUPPORTED, true)) {
                return $candidate;
            }
        }

        return self::DEFAULT;
    }

    /**
     * 解析 Accept-Language header，提取最匹配的支持语言
     */
    private function parseAcceptLanguage(?string $header): ?string
    {
        if (! $header) {
            return null;
        }

        $lc = strtolower($header);

        if (str_contains($lc, 'zh-tw') || str_contains($lc, 'zh-hk') || str_contains($lc, 'zh-mo')) {
            return 'zh-HK';
        }
        if (str_contains($lc, 'zh-cn') || str_contains($lc, 'zh-sg')) {
            return 'zh-CN';
        }
        if (str_starts_with($lc, 'en')) {
            return 'en';
        }

        return null;
    }
}
