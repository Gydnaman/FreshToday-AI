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
 *  5. greeting/tip/name/description 必须是非空字符串
 *  6. 所有 ingredients 都必须是非空字符串，并与 availableProducts 中的候选名称完全一致
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

        if (! $this->isNonEmptyString($data['greeting'])
            || ! $this->isNonEmptyString($data['tip'])) {
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
            if (! $this->isNonEmptyString($meal['name'])
                || ! $this->isNonEmptyString($meal['description'])) {
                return false;
            }
            if (! is_array($meal['ingredients'])) {
                return false;
            }
            $allIngredients = array_merge($allIngredients, $meal['ingredients']);
        }

        // 严格校验：所有 ingredients 都必须是商品候选名称的精确成员
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
     * 严格校验：所有 ingredients 都必须是非空字符串，并精确匹配商品候选名称。
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
            if (! is_string($ingredient)
                || trim($ingredient) === ''
                || ! in_array($ingredient, $availableProducts, true)) {
                return false;
            }
        }

        return true;
    }

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
