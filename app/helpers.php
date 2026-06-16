<?php

/**
 * GreenBite i18n 翻译辅助函数
 * 详见 docs/i18n/PLAN-i18n.md
 *
 * 背景：Laravel 12 的 `__()` 内建从 resources/lang/{locale}.json 加载翻译。
 * 3 份 JSON SSOT 同步在：
 *   - docs/i18n/locales/{zh-HK,en,zh-CN}.json   （前端 fetch 源）
 *   - resources/lang/{zh-HK,en,zh-CN}.json       （Laravel 服务端 __() 源）
 *
 * 同步脚本（手工/或 CI）：
 *   cp docs/i18n/locales/zh-HK.json resources/lang/zh-HK.json
 *   cp docs/i18n/locales/en.json    resources/lang/en.json
 *   cp docs/i18n/locales/zh-CN.json resources/lang/zh-CN.json
 *
 * 嵌套 key 用点号表示：`__('home.title')` 解析为 dict.home.title
 * Laravel 内建 JSON 翻译只支持扁平 key（dot 不解析），所以提供
 * 兼容：i18n() 函数支持嵌套 key。
 */
if (! function_exists('i18n')) {
    /**
     * 取嵌套 key 的翻译值，未命中时回退到 key 自身（方便排查）。
     *
     * @param  string  $key  点号分隔的 key 路径，如 'home.title'
     * @param  array  $replace  占位符替换，如 ['name' => 'Tom']
     * @param  string|null  $locale  显式 locale；不传则用当前 app()->getLocale()
     */
    function i18n(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $path = resource_path('lang/'.$locale.'.json');

        $value = null;
        if (is_file($path)) {
            $dict = json_decode((string) file_get_contents($path), true) ?: [];
            $value = data_get($dict, $key);
        }

        if ($value === null) {
            // 兜底：尝试 en
            $fallback = resource_path('lang/en.json');
            if (is_file($fallback) && $locale !== 'en') {
                $dict = json_decode((string) file_get_contents($fallback), true) ?: [];
                $value = data_get($dict, $key);
            }
        }

        if ($value === null) {
            return $key;
        }

        foreach ($replace as $k => $v) {
            $value = str_replace(':'.$k, (string) $v, $value);
            $value = str_replace('{'.$k.'}', (string) $v, $value);
        }

        return (string) $value;
    }
}
