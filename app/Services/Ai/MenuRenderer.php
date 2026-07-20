<?php

namespace App\Services\Ai;

/**
 * 菜单渲染器
 *
 * 职责：把结构化 JSON 菜单渲染成纯文本或 HTML。
 *  - renderTextFromJson: 纯文本，供 menu_content 字段使用（兼容现有前端）
 *  - renderHtmlFromJson: HTML，食材名包装成可点击链接（跳转到 catalog 页锚点）
 *
 * 纯文本输出格式：
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
 *
 * HTML 输出格式（语义化标签 + Tailwind 类）：
 *   <div class="ai-menu">
 *     <p class="greeting">{greeting}</p>
 *     <div class="meal">
 *       <h4>Breakfast: {name}</h4>
 *       <p>{description with <a>ingredients</a>}</p>
 *     </div>
 *     ...
 *     <p class="tip">💡 Tip: {tip}</p>
 *   </div>
 */
class MenuRenderer
{
    /**
     * 渲染纯文本
     *
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

    /**
     * 渲染 HTML（食材名包装成链接）
     *
     * @param  array{greeting:string,meals:array<int,array{type:string,name:string,ingredients:array<int,string>,description:string}>,tip:string}  $json
     * @param  array<string,int>  $productMap  商品名 → 商品 ID 映射（用于生成链接）
     * @return string HTML 片段（所有非食材文本已转义，防 XSS）
     */
    public static function renderHtmlFromJson(array $json, array $productMap): string
    {
        $html = '<div class="ai-menu">';

        // Greeting
        $html .= '<p class="greeting text-gray-700 mb-4">'.e($json['greeting']).'</p>';

        // Meals
        foreach ($json['meals'] as $meal) {
            $type = ucfirst($meal['type']);
            $html .= '<div class="meal mb-4">';
            $html .= '<h4 class="font-bold text-gray-900 mb-1">'.e($type).': '.e($meal['name']).'</h4>';
            $html .= '<p class="text-gray-600 text-sm">'.e($meal['description']).'</p>';
            $html .= '</div>';
        }

        // Tip
        $html .= '<p class="tip text-gray-700 mt-4 pt-4 border-t border-gray-200">💡 Tip: '.e($json['tip']).'</p>';

        $html .= '</div>';

        // 统一替换所有食材名为链接（在 HTML 转义后执行，避免 \b 边界失效）
        $html = self::linkifyIngredients($html, $json, $productMap);

        return $html;
    }

    /**
     * 在 HTML 中把食材名替换为链接
     *
     * 策略：遍历所有 meals 的 ingredients，对每个有映射的食材名，
     *       在 HTML 中查找并替换第一次出现为 <a> 标签。
     *
     * 注意：必须在 e() 转义后执行，因为转义可能改变字符（如 " 变成 &quot;），
     *       但食材名本身通常不含特殊字符，e($ingredient) === $ingredient。
     *
     * @param  array{meals:array<int,array{ingredients:array<int,string>}>}  $json
     * @param  array<string,int>  $productMap
     */
    private static function linkifyIngredients(string $html, array $json, array $productMap): string
    {
        foreach ($json['meals'] as $meal) {
            foreach ($meal['ingredients'] as $ingredient) {
                // 模糊匹配：查找商品名包含食材名 OR 食材名包含商品名的商品
                $matchedProductId = self::fuzzyMatchProduct($ingredient, $productMap);

                if ($matchedProductId === null) {
                    continue; // 无匹配，保持纯文本
                }

                $escapedIngredient = e($ingredient);
                $link = '<a href="/catalog#product-'.$matchedProductId.'" class="text-green-600 hover:text-green-700 underline font-medium">'.$escapedIngredient.'</a>';

                // 用 preg_replace 的 limit=1 只替换第一次出现（避免同一食材多次出现全部替换）
                // 不用 \b 单词边界，因为转义后的 HTML 实体（如 &quot;）会破坏边界匹配
                $html = preg_replace(
                    '/'.preg_quote($escapedIngredient, '/').'/',
                    $link,
                    $html,
                    1
                );
            }
        }

        return $html;
    }

    /**
     * 模糊匹配商品 ID
     *
     * 匹配规则（双向，大小写不敏感）：
     *  - 商品名包含食材名（如"本地有機菜心"包含"菜心"）
     *  - 食材名包含商品名（如"有機菜心"包含"菜心"，较少见）
     *
     * @param  array<string,int>  $productMap  商品名 → 商品 ID
     * @return int|null 匹配到的商品 ID，无匹配返回 null
     */
    private static function fuzzyMatchProduct(string $ingredient, array $productMap): ?int
    {
        $ingredientLower = mb_strtolower(trim($ingredient));

        foreach ($productMap as $productName => $productId) {
            $productLower = mb_strtolower($productName);

            if (mb_strpos($productLower, $ingredientLower) !== false
                || mb_strpos($ingredientLower, $productLower) !== false) {
                return $productId;
            }
        }

        return null;
    }
}
