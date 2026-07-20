<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo 演示专用 Seeder
 *
 * 用途：演示前快速准备 3 个不同画像的 demo 用户，
 *       登录后可直接触发生成个性化 AI 菜单。
 *
 * 使用：
 *   php artisan db:seed --class=DemoSeeder
 *
 * 三个用户画像：
 *  1. demo-vegan@greenbite.hk    素食健身者（低预算、新手厨艺）
 *  2. demo-family@greenbite.hk   家庭主厨（4 人家庭、中等预算、中级厨艺）
 *  3. demo-keto@greenbite.hk     生酮白领（高预算、高级厨艺）
 *
 * 密码统一：demo1234
 *
 * 幂等：updateOrCreate，可重复执行。
 */
class DemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'demo1234';

    public function run(): void
    {
        $this->command->info('🎭 Seeding demo users for presentation...');

        $this->seedVeganUser();
        $this->seedFamilyUser();
        $this->seedKetoUser();

        $this->command->info('🎭 Done. 3 demo users ready.');
        $this->command->info('');
        $this->command->info('Demo 登录信息：');
        $this->command->info('  1. demo-vegan@greenbite.hk  / '.self::DEMO_PASSWORD.'  (素食健身者)');
        $this->command->info('  2. demo-family@greenbite.hk / '.self::DEMO_PASSWORD.'  (家庭主厨)');
        $this->command->info('  3. demo-keto@greenbite.hk   / '.self::DEMO_PASSWORD.'  (生酮白领)');
    }

    /**
     * 画像 1：素食健身者
     *  - 年轻白领，健身减脂，预算紧张，厨艺新手
     *  - AI 应推荐：简单快手、高蛋白素食、低成本食材
     */
    private function seedVeganUser(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo-vegan@greenbite.hk'],
            [
                'name' => 'Vegan Athlete (Demo)',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'locale' => 'zh',
                'is_admin' => false,
            ],
        );

        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'usage_purpose' => 'Fitness and weight loss',
                'dietary_habits' => 'Vegan',
                'goals' => 'Build muscle, lose fat',
                'allergies' => ['nuts'],
                'household_size' => 1,
                'cooking_skill' => 'Beginner',
                'budget_hkd' => 500,
            ],
        );

        $this->command->info("  ✓ Vegan Athlete (id={$user->id})");
    }

    /**
     * 画像 2：家庭主厨
     *  - 妈妈，4 人家庭，注重营养均衡，预算中等，厨艺中级
     *  - AI 应推荐：家庭份量、孩子友好、多样搭配
     */
    private function seedFamilyUser(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo-family@greenbite.hk'],
            [
                'name' => 'Family Chef (Demo)',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'locale' => 'zh',
                'is_admin' => false,
            ],
        );

        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'usage_purpose' => 'Family daily meals',
                'dietary_habits' => 'No restriction',
                'goals' => 'Balanced nutrition for kids',
                'allergies' => ['shellfish'],
                'household_size' => 4,
                'cooking_skill' => 'Intermediate',
                'budget_hkd' => 1500,
            ],
        );

        $this->command->info("  ✓ Family Chef (id={$user->id})");
    }

    /**
     * 画像 3：生酮白领
     *  - 高收入专业人士，生酮饮食，预算充足，厨艺高级
     *  - AI 应推荐：低碳高脂、精致食材、复杂烹饪
     */
    private function seedKetoUser(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo-keto@greenbite.hk'],
            [
                'name' => 'Keto Professional (Demo)',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'locale' => 'zh',
                'is_admin' => false,
            ],
        );

        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            [
                'usage_purpose' => 'Ketogenic lifestyle',
                'dietary_habits' => 'Keto, low-carb, high-fat',
                'goals' => 'Maintain ketosis, mental clarity',
                'allergies' => [],
                'household_size' => 2,
                'cooking_skill' => 'Advanced',
                'budget_hkd' => 3000,
            ],
        );

        $this->command->info("  ✓ Keto Professional (id={$user->id})");
    }
}
