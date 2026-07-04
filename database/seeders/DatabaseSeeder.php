<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * GreenBite 种子数据（Sprint 1 完整版）
 *
 * 幂等：全部使用 updateOrCreate，可重复执行。
 * Sprint 1 范围：web 端 商品→加购→购物车→结算 + 订阅 演示数据。
 *
 * 覆盖：
 *  - 2 个用户（demo 客户 + admin 管理员）
 *  - 6 个一级分类 + 8 个二级分类
 *  - 24 个真实风格产品（HK$ 价格、HK 农场 origin、is_organic 分布、含 stock=0）
 *  - 3 个订阅套餐（weekly / biweekly / monthly）
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Seeding GreenBite Sprint-1 demo data...');

        $this->seedUsers();
        $this->seedCategories();
        $this->seedProducts();
        $this->seedSubscriptionPlans();

        $this->command->info('🌱 Done.');
    }

    private function seedUsers(): void
    {
        $users = [
            [
                'name' => 'Demo Customer',
                'email' => 'demo@greenbite.hk',
                'password' => Hash::make('password'),
                'locale' => 'zh-HK',
                'is_admin' => false,
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@greenbite.hk',
                'password' => Hash::make('password'),
                'locale' => 'zh-HK',
                'is_admin' => true,
            ],
        ];
        $count = 0;
        foreach ($users as $u) {
            User::updateOrCreate(['email' => $u['email']], $u);
            $count++;
        }
        $this->command->info("  ✓ Users: {$count} (demo + admin)");
    }

    private function seedCategories(): void
    {
        $topCategories = [
            ['name' => '蔬菜', 'slug' => 'vegetables', 'sort_order' => 10],
            ['name' => '水果', 'slug' => 'fruits',     'sort_order' => 20],
            ['name' => '肉蛋', 'slug' => 'meat-eggs',  'sort_order' => 30],
            ['name' => '海鮮', 'slug' => 'seafood',    'sort_order' => 40],
            ['name' => '主食', 'slug' => 'staples',    'sort_order' => 50],
            ['name' => '調味', 'slug' => 'seasonings', 'sort_order' => 60],
        ];

        $topModels = [];
        foreach ($topCategories as $cat) {
            $topModels[$cat['slug']] = Category::updateOrCreate(
                ['slug' => $cat['slug']],
                $cat + ['parent_id' => null],
            );
        }

        $subCategories = [
            ['parent' => 'vegetables', 'name' => '葉菜',   'slug' => 'leafy-greens',  'sort_order' => 11],
            ['parent' => 'vegetables', 'name' => '根莖',   'slug' => 'root-vegetables', 'sort_order' => 12],
            ['parent' => 'fruits',     'name' => '時令',   'slug' => 'seasonal',       'sort_order' => 21],
            ['parent' => 'fruits',     'name' => '柑橘',   'slug' => 'citrus',         'sort_order' => 22],
            ['parent' => 'meat-eggs',  'name' => '雞蛋',   'slug' => 'eggs',           'sort_order' => 31],
            ['parent' => 'seafood',    'name' => '鮮魚',   'slug' => 'fresh-fish',     'sort_order' => 41],
            ['parent' => 'staples',    'name' => '米麵',   'slug' => 'rice-noodle',    'sort_order' => 51],
            ['parent' => 'seasonings', 'name' => '醬料',   'slug' => 'sauces',         'sort_order' => 61],
        ];
        $subModels = [];
        foreach ($subCategories as $sub) {
            $subModels[$sub['slug']] = Category::updateOrCreate(
                ['slug' => $sub['slug']],
                [
                    'name' => $sub['name'],
                    'slug' => $sub['slug'],
                    'parent_id' => $topModels[$sub['parent']]->id,
                    'sort_order' => $sub['sort_order'],
                ],
            );
        }
        $this->command->info('  ✓ Categories: '.(count($topCategories) + count($subCategories)).' (6 top + '.count($subCategories).' sub)');
    }

    private function seedProducts(): void
    {
        // 拉已存在的 sub models（依赖 seedCategories 已跑过）
        $subModels = Category::whereNotNull('parent_id')->get()->keyBy('slug');

        $products = [
            // 葉菜 / 有机
            ['cat' => 'leafy-greens', 'name' => '本地有機菜心',     'price' => 28.00, 'stock' => 50, 'is_organic' => 1, 'origin' => '元朗八鄉農場',     'carbon' => 0.310, 'image' => 'https://placehold.co/400x400/4ade80/ffffff?text='.urlencode('菜心'), 'desc' => '當日採摘，水嫩清甜，香港本地有機認證。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機白菜',     'price' => 24.00, 'stock' => 60, 'is_organic' => 1, 'origin' => '元朗新田農場',     'carbon' => 0.290, 'image' => 'https://placehold.co/400x400/4ade80/ffffff?text='.urlencode('白菜'), 'desc' => '本地當造白菜，適合清炒或滾湯。'],
            ['cat' => 'leafy-greens', 'name' => '本地西洋菜',       'price' => 22.00, 'stock' => 40, 'is_organic' => 0, 'origin' => '粉嶺鶴藪',         'carbon' => 0.250, 'image' => 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=400', 'desc' => '鮮嫩西洋菜，廣東滾湯必備。'],
            // 根莖
            ['cat' => 'root-vegetables', 'name' => '本地有機紅蘿蔔',  'price' => 18.00, 'stock' => 80, 'is_organic' => 1, 'origin' => '打鼓嶺農場',       'carbon' => 0.180, 'image' => 'https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=400', 'desc' => '帶泥土清香，適合煲湯或榨汁。'],
            ['cat' => 'root-vegetables', 'name' => '本地黃薑',        'price' => 32.00, 'stock' => 30, 'is_organic' => 0, 'origin' => '大埔林村',         'carbon' => 0.220, 'image' => 'https://images.unsplash.com/photo-1615485290382-441e4d049cb5?w=400', 'desc' => '新鮮黃薑，辛香濃郁。'],
            ['cat' => 'root-vegetables', 'name' => '本地紫薯',        'price' => 38.00, 'stock' => 0,  'is_organic' => 1, 'origin' => '西貢北潭涌',       'carbon' => 0.270, 'image' => 'https://placehold.co/400x400/a855f7/ffffff?text='.urlencode('紫薯'), 'desc' => '當造紫薯已售罄，下批預計下週到貨。'],
            // 時令水果
            ['cat' => 'seasonal',   'name' => '本地沙田柚',         'price' => 48.00, 'stock' => 25, 'is_organic' => 0, 'origin' => '沙田瀝源',         'carbon' => 0.420, 'image' => 'https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?w=400', 'desc' => '果肉飽滿多汁，秋季限定。'],
            ['cat' => 'seasonal',   'name' => '本地楊桃',           'price' => 35.00, 'stock' => 20, 'is_organic' => 1, 'origin' => '大埔大埔滘',       'carbon' => 0.380, 'image' => 'https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?w=400', 'desc' => '切片後星形漂亮，酸甜開胃。'],
            ['cat' => 'seasonal',   'name' => '本地木瓜',           'price' => 42.00, 'stock' => 18, 'is_organic' => 1, 'origin' => '元朗流浮山',       'carbon' => 0.350, 'image' => 'https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?w=400', 'desc' => '完熟木瓜，鮮甜軟糯。'],
            // 柑橘
            ['cat' => 'citrus',     'name' => '本地有機臍橙',       'price' => 56.00, 'stock' => 30, 'is_organic' => 1, 'origin' => '北區蓮麻坑',       'carbon' => 0.480, 'image' => 'https://images.unsplash.com/photo-1547514701-42782101795e?w=400', 'desc' => '皮薄多汁，維 C 豐富。'],
            ['cat' => 'citrus',     'name' => '本地青檸',           'price' => 28.00, 'stock' => 40, 'is_organic' => 0, 'origin' => '長洲',             'carbon' => 0.260, 'image' => 'https://images.unsplash.com/photo-1590502593747-42a996133562?w=400', 'desc' => '酸味濃郁，調飲、海鮮必備。'],
            // 雞蛋
            ['cat' => 'eggs',       'name' => '本地走地雞蛋（10 隻）', 'price' => 68.00, 'stock' => 50, 'is_organic' => 1, 'origin' => '元朗雞場',         'carbon' => 1.250, 'image' => 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?w=400', 'desc' => '放山雞隻，蛋香濃郁。'],
            ['cat' => 'eggs',       'name' => '本地初生蛋（6 隻）',  'price' => 58.00, 'stock' => 0,  'is_organic' => 1, 'origin' => '打鼓嶺',           'carbon' => 1.180, 'image' => 'https://images.unsplash.com/photo-1569288063643-5d29ad6dfc8d?w=400', 'desc' => '初生蛋體型細小，蛋味極濃（已售罄）。'],
            // 鮮魚
            ['cat' => 'fresh-fish', 'name' => '本地新鮮烏頭',       'price' => 88.00, 'stock' => 12, 'is_organic' => 0, 'origin' => '西貢漁民直送',     'carbon' => 2.100, 'image' => 'https://images.unsplash.com/photo-1559827260-dc66d52bef19?w=400', 'desc' => '當日捕撈，肉質鮮甜。'],
            ['cat' => 'fresh-fish', 'name' => '本地龍躉柳',         'price' => 168.00, 'stock' => 8,  'is_organic' => 0, 'origin' => '南丫島',           'carbon' => 3.500, 'image' => 'https://images.unsplash.com/photo-1565280654386-466c2a1a4a13?w=400', 'desc' => '厚切魚柳，適合清蒸。'],
            ['cat' => 'fresh-fish', 'name' => '本地蝦仁',           'price' => 98.00, 'stock' => 20, 'is_organic' => 0, 'origin' => '香港仔魚市場',     'carbon' => 4.200, 'image' => 'https://images.unsplash.com/photo-1565680018434-b513d5e5fd47?w=400', 'desc' => '急凍新鮮蝦仁，已剝殼。'],
            // 米麵
            ['cat' => 'rice-noodle', 'name' => '本地有機絲苗米 2kg',  'price' => 78.00, 'stock' => 40, 'is_organic' => 1, 'origin' => '元朗新田',         'carbon' => 1.050, 'image' => 'https://images.unsplash.com/photo-1568347355280-d33fdf77d42a?w=400', 'desc' => '香港少見本地稻米，飯香軟糯。'],
            ['cat' => 'rice-noodle', 'name' => '本地蝦子麵',         'price' => 32.00, 'stock' => 60, 'is_organic' => 0, 'origin' => '長洲老字號',       'carbon' => 0.880, 'image' => 'https://images.unsplash.com/photo-1607330289024-1535c6b4e1c1?w=400', 'desc' => '傳統竹昇麵，爽口彈牙。'],
            ['cat' => 'rice-noodle', 'name' => '本地米線',           'price' => 28.00, 'stock' => 45, 'is_organic' => 0, 'origin' => '大埔',             'carbon' => 0.920, 'image' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400', 'desc' => '無添加米線，健康之選。'],
            // 醬料
            ['cat' => 'sauces',     'name' => '本地手工豉油',       'price' => 45.00, 'stock' => 35, 'is_organic' => 0, 'origin' => '九龍城老醬園',     'carbon' => 0.520, 'image' => 'https://images.unsplash.com/photo-1599909366516-6c1f0fcaa0a5?w=400', 'desc' => '古法釀造 180 天，醬香醇厚。'],
            ['cat' => 'sauces',     'name' => '本地XO醬',           'price' => 88.00, 'stock' => 20, 'is_organic' => 0, 'origin' => '上環老牌醬廠',     'carbon' => 0.950, 'image' => 'https://images.unsplash.com/photo-1604908554027-6f2b16e5b8e3?w=400', 'desc' => '瑤柱蝦米熬製，送禮自用皆宜。'],
            ['cat' => 'sauces',     'name' => '本地蜜糖（龍眼蜜）',  'price' => 120.00, 'stock' => 15, 'is_organic' => 1, 'origin' => '沙頭角蜂農',       'carbon' => 0.380, 'image' => 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=400', 'desc' => '100% 純天然龍眼蜜，無添加。'],
            // 補充素食
            ['cat' => 'rice-noodle', 'name' => '本地有機豆腐 3 盒裝',  'price' => 38.00, 'stock' => 30, 'is_organic' => 1, 'origin' => '元朗豆腐廠',       'carbon' => 0.680, 'image' => 'https://images.unsplash.com/photo-1620706857370-e1b9770e8bb1?w=400', 'desc' => '本地黃豆即日製，嫩滑清香。'],
            ['cat' => 'root-vegetables', 'name' => '本地粟米',       'price' => 20.00, 'stock' => 0,  'is_organic' => 0, 'origin' => '屯門',             'carbon' => 0.230, 'image' => 'https://images.unsplash.com/photo-1551754655-cd27e38d2076?w=400', 'desc' => '本地甜粟米（已售罄，請預購下批）。'],
        ];

        $count = 0;
        foreach ($products as $p) {
            $catId = $subModels[$p['cat']]->id ?? null;
            Product::updateOrCreate(
                ['name' => $p['name']],
                [
                    'name' => $p['name'],
                    'description' => $p['desc'],
                    'price' => $p['price'],
                    'image' => $p['image'],
                    'carbon_footprint' => $p['carbon'],
                    'stock' => $p['stock'],
                    'category_id' => $catId,
                    'is_organic' => (bool) $p['is_organic'],
                    'origin' => $p['origin'],
                ],
            );
            $count++;
        }
        $this->command->info("  ✓ Products: {$count}");
    }

    private function seedSubscriptionPlans(): void
    {
        $plans = [
            [
                'name' => 'Weekly Starter',
                'description' => '每週配送 5 份當造本地蔬菜，適合 1-2 人小家庭。免費本地送貨。',
                'price' => 280.00,
                'duration' => 7,
                'cycle' => 'weekly',
                'is_active' => 1,
                'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400',
                'features' => '5 份當造蔬菜|本地有機認證|免費送貨|碳足跡報告',
            ],
            [
                'name' => 'Biweekly Family',
                'description' => '每兩週配送 12 份蔬菜 + 4 份雞蛋，適合 3-4 人家庭。',
                'price' => 580.00,
                'duration' => 14,
                'cycle' => 'biweekly',
                'is_active' => 1,
                'image' => 'https://images.unsplash.com/photo-1488459716781-31db52582fe9?w=400',
                'features' => '12 份蔬菜|4 份走地雞蛋|免費送貨|AI 菜單推薦|碳足跡報告',
            ],
            [
                'name' => 'Monthly Premium',
                'description' => '每月配送 30 份混合食材（蔬菜+肉類+海鮮），含 AI 個性化菜單。',
                'price' => 1280.00,
                'duration' => 30,
                'cycle' => 'monthly',
                'is_active' => 1,
                'image' => 'https://images.unsplash.com/photo-1490818387583-1baba5e638af?w=400',
                'features' => '30 份混合食材|肉類+海鮮|AI 菜單|免費送貨|優先客服|碳足跡報告',
            ],
        ];
        $count = 0;
        foreach ($plans as $p) {
            SubscriptionPlan::updateOrCreate(['name' => $p['name']], $p);
            $count++;
        }
        $this->command->info("  ✓ Subscription Plans: {$count}");
    }
}
