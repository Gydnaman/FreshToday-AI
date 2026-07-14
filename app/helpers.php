<?php

/**
 * GreenBite i18n 翻译辅助函数
 *
 * 约定：
 *   - resources/lang/{zh,en,zhhk}.json 是 SSOT 翻译源
 *   - locale 标识符 zh-CN / zh-HK 在内部映射为文件名 zh / zhhk
 *
 * 嵌套 key 用点号表示：i18n('home.title') 解析为 dict.home.title
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
        $locale = match ($locale) {
            'zh-CN', 'zh-cn', 'zh' => 'zh',
            'zh-HK', 'zh-hk', 'zh-TW', 'zh-tw', 'zh-MO', 'zh-mo', 'zhhk' => 'zhhk',
            'en', 'en-US', 'en-GB', 'en-us', 'en-gb' => 'en',
            default => 'en',
        };
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
