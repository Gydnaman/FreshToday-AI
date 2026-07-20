<?php

namespace App\Services\Ai;

/**
 * 菜单渲染器
 *
 * 职责：把结构化 JSON 菜单渲染成纯文本，供 menu_content 字段使用（兼容现有前端）。
 *
 * 输出格式：
 *   {greeting}
 *
 *   Breakfast: {name}
 *   {description}
 *
 *   Lunch: {name}
 *   {description}
 *
 *   Dinner: {name}
 *   {description}
 *
 *   💡 Tip: {tip}
 */
class MenuRenderer
{
    /**
     * @param  array{greeting:string,meals:array<int,array{type:string,name:string,ingredients:array<int,string>,description:string}>,tip:string}  $json
     */
    public static function renderTextFromJson(array $json): string
    {
        $parts = [$json['greeting']];

        foreach ($json['meals'] as $meal) {
            $type = ucfirst($meal['type']);
            $parts[] = ''; // 空行
            $parts[] = "{$type}: {$meal['name']}";
            $parts[] = $meal['description'];
        }

        $parts[] = '';
        $parts[] = "💡 Tip: {$json['tip']}";

        return implode("\n", $parts);
    }
}
