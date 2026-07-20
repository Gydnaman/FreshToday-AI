<?php

namespace App\Services\Ai;

/**
 * AI 菜单输出校验器
 *
 * 职责：对 Provider 返回的自由文本或结构化 JSON 做合法性校验，
 *       拦截跑题、注入、拒绝回答、广告链接等异常输出。
 *
 * 校验规则（自由文本）：
 *  1. 长度 ∈ [MIN_LENGTH, MAX_LENGTH]
 *  2. 不含 BLACKLIST 关键词（注入/拒绝/代码块/URL/FALLBACK 信号）
 *  3. 至少提到 1 个 availableProducts 中的商品名（防止完全跑题）
 *
 * 校验规则（JSON）：
 *  1. 必须含 greeting / meals / tip 三个 key
 *  2. meals 必须是数组且 count=3
 *  3. 每个 meal 必须含 type/name/ingredients/description
 *  4. type ∈ {breakfast, lunch, dinner}
 *  5. 所有 ingredients 都必须能在 availableProducts 中模糊匹配到（严格约束）
 *     - 模糊匹配：商品名包含食材名 OR 食材名包含商品名（如"菜心"匹配"本地有機菜心"）
 *     - 任何 1 个食材匹配不到 → 校验失败 → 走 fallback
 */
class MenuOutputValidator
{
    public const MIN_LENGTH = 50;

    public const MAX_LENGTH = 2000;

    public const BLACKLIST = [
        'as an ai',
        'i cannot',
        "i'm sorry",
        '```',
        'http://',
        'https://',
        'fallback',
    ];

    private const VALID_MEAL_TYPES = ['breakfast', 'lunch', 'dinner'];

    /**
     * 校验自由文本输出
     *
     * @param  array<int,string>  $availableProducts
     */
    public function validate(string $content, array $availableProducts): bool
    {
        $len = mb_strlen($content);
        if ($len < self::MIN_LENGTH || $len > self::MAX_LENGTH) {
            return false;
        }

        $lower = mb_strtolower($content);
        foreach (self::BLACKLIST as $word) {
            if (mb_strpos($lower, $word) !== false) {
                return false;
            }
        }

        return $this->mentionsAnyProduct($content, $availableProducts);
    }

    /**
     * 校验结构化 JSON 输出
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,string>  $availableProducts
     */
    public function validateJson(array $data, array $availableProducts): bool
    {
        if (! isset($data['greeting'], $data['meals'], $data['tip'])) {
            return false;
        }

        if (! is_array($data['meals']) || count($data['meals']) !== 3) {
            return false;
        }

        $allIngredients = [];
        foreach ($data['meals'] as $meal) {
            if (! is_array($meal)) {
                return false;
            }
            if (! isset($meal['type'], $meal['name'], $meal['ingredients'], $meal['description'])) {
                return false;
            }
            if (! in_array($meal['type'], self::VALID_MEAL_TYPES, true)) {
                return false;
            }
            if (! is_array($meal['ingredients'])) {
                return false;
            }
            $allIngredients = array_merge($allIngredients, $meal['ingredients']);
        }

        // 严格校验：所有 ingredients 都必须能在商品库中模糊匹配到
        // （已覆盖"至少提到 1 个商品"的宽松校验场景，无需重复检查）
        return $this->allIngredientsInProducts($allIngredients, $availableProducts);
    }

    /**
     * 检查文本是否提到任一商品名（大小写不敏感）
     *
     * @param  array<int,string>  $availableProducts
     */
    private function mentionsAnyProduct(string $text, array $availableProducts): bool
    {
        $lower = mb_strtolower($text);
        foreach ($availableProducts as $product) {
            if (mb_strpos($lower, mb_strtolower($product)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 严格校验：所有 ingredients 都必须能在商品库中模糊匹配到
     *
     * 模糊匹配规则（双向）：
     *  - 商品名包含食材名（如"本地有機菜心"包含"菜心"）
     *  - 食材名包含商品名（如"有機菜心"包含"菜心"，较少见）
     *
     * @param  array<int,string>  $ingredients
     * @param  array<int,string>  $availableProducts
     */
    private function allIngredientsInProducts(array $ingredients, array $availableProducts): bool
    {
        if (empty($ingredients)) {
            return false;
        }

        foreach ($ingredients as $ingredient) {
            $found = false;
            $ingredientLower = mb_strtolower(trim($ingredient));

            foreach ($availableProducts as $product) {
                $productLower = mb_strtolower($product);

                // 双向模糊匹配
                if (mb_strpos($productLower, $ingredientLower) !== false
                    || mb_strpos($ingredientLower, $productLower) !== false) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                return false; // 任何 1 个食材匹配不到就失败
            }
        }

        return true;
    }
}
