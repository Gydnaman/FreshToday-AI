<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * 扩充演示商品：105 个新食材（含新子分类「鮮肉」），配图用本地下载的 Unsplash 免费图片。
 *
 * 运行：php artisan db:seed --class=ExtraProductsSeeder
 * 图片：storage/app/public/products/seed/*.jpg（public/storage 软链接已建立）
 */
class ExtraProductsSeeder extends Seeder
{
    public function run(): void
    {
        // 新子分类：鮮肉（挂在 肉蛋 下，补全肉类商品的归属）
        Category::updateOrCreate(
            ['slug' => 'fresh-meat'],
            ['name' => '鮮肉', 'parent_id' => Category::where('slug', 'meat-eggs')->value('id')],
        );

        $subModels = Category::whereNotNull('parent_id')->get()->keyBy('slug');

        $products = [
            // ===== 葉菜 leafy-greens (14) =====
            ['cat' => 'leafy-greens', 'name' => '本地有機生菜',   'price' => 22.00, 'stock' => 45, 'is_organic' => 1, 'origin' => '元朗八鄉農場',   'carbon' => 0.280, 'image' => 'products/seed/lettuce.jpg',   'desc' => '爽脆多汁，沙律、包肉食法皆宜。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機菠菜',   'price' => 26.00, 'stock' => 40, 'is_organic' => 1, 'origin' => '粉嶺鶴藪農場',   'carbon' => 0.300, 'image' => 'products/seed/spinach.jpg',   'desc' => '鐵質豐富，蒜蓉清炒最鮮甜。'],
            ['cat' => 'leafy-greens', 'name' => '本地羽衣甘藍',   'price' => 32.00, 'stock' => 25, 'is_organic' => 1, 'origin' => '大埔林村農場',   'carbon' => 0.320, 'image' => 'products/seed/kale.jpg',      'desc' => '超級食物，沙律或烤脆片均可。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機小白菜', 'price' => 24.00, 'stock' => 50, 'is_organic' => 1, 'origin' => '元朗新田農場',   'carbon' => 0.270, 'image' => 'products/seed/choysum.jpg',   'desc' => '嫩甜小白菜，滾湯快熟之選。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機油麥菜', 'price' => 25.00, 'stock' => 45, 'is_organic' => 1, 'origin' => '上水古洞農場',   'carbon' => 0.260, 'image' => 'products/seed/choysum.jpg',   'desc' => '梗脆葉嫩，豆豉鯪魚炒最經典。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機芥蘭',   'price' => 28.00, 'stock' => 38, 'is_organic' => 1, 'origin' => '打鼓嶺農場',     'carbon' => 0.290, 'image' => 'products/seed/choysum.jpg',   'desc' => '爽甜無渣，薑汁炒或白灼皆可。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機通菜',   'price' => 22.00, 'stock' => 42, 'is_organic' => 1, 'origin' => '元朗八鄉農場',   'carbon' => 0.250, 'image' => 'products/seed/mixedveg.jpg',  'desc' => '椒絲腐乳炒，大排檔風味。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機莧菜',   'price' => 26.00, 'stock' => 30, 'is_organic' => 1, 'origin' => '粉嶺鶴藪農場',   'carbon' => 0.280, 'image' => 'products/seed/spinach.jpg',   'desc' => '紅葉莧菜，蒜片滾湯補鐵。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機韭菜',   'price' => 24.00, 'stock' => 35, 'is_organic' => 1, 'origin' => '大埔林村農場',   'carbon' => 0.240, 'image' => 'products/seed/herbs.jpg',     'desc' => '香氣濃郁，炒蛋、做餃子餡一流。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機豆苗',   'price' => 30.00, 'stock' => 28, 'is_organic' => 1, 'origin' => '元朗新田農場',   'carbon' => 0.310, 'image' => 'products/seed/vegbowl.jpg',   'desc' => '嫩芽鮮甜，上湯浸最顯功架。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機西蘭花', 'price' => 32.00, 'stock' => 33, 'is_organic' => 1, 'origin' => '打鼓嶺農場',     'carbon' => 0.330, 'image' => 'products/seed/broccoli.jpg',  'desc' => '維 C 豐富，白灼或蒜蓉炒。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機椰菜',   'price' => 20.00, 'stock' => 48, 'is_organic' => 1, 'origin' => '上水古洞農場',   'carbon' => 0.230, 'image' => 'products/seed/cabbage.jpg',   'desc' => '清甜爽脆，手撕炒更惹味。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機大白菜', 'price' => 22.00, 'stock' => 44, 'is_organic' => 1, 'origin' => '元朗八鄉農場',   'carbon' => 0.240, 'image' => 'products/seed/cabbage.jpg',   'desc' => '煲湯清甜，做酸菜鍋底亦佳。'],
            ['cat' => 'leafy-greens', 'name' => '本地有機芹菜',   'price' => 26.00, 'stock' => 30, 'is_organic' => 1, 'origin' => '粉嶺鶴藪農場',   'carbon' => 0.260, 'image' => 'products/seed/mixedveg.jpg',  'desc' => '爽脆降壓，炒牛肉或榨汁皆宜。'],

            // ===== 根莖 root-vegetables (16) =====
            ['cat' => 'root-vegetables', 'name' => '本地有機甘筍',   'price' => 18.00, 'stock' => 60, 'is_organic' => 1, 'origin' => '打鼓嶺農場',   'carbon' => 0.180, 'image' => 'products/seed/carrot.jpg',    'desc' => '甜脆多汁，煲湯榨汁皆可。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機白蘿蔔', 'price' => 16.00, 'stock' => 55, 'is_organic' => 1, 'origin' => '元朗新田農場', 'carbon' => 0.170, 'image' => 'products/seed/radish.jpg',    'desc' => '水分充足，蘿蔔牛腩絕配。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機番茄',   'price' => 28.00, 'stock' => 40, 'is_organic' => 1, 'origin' => '大埔林村農場', 'carbon' => 0.250, 'image' => 'products/seed/tomato.jpg',    'desc' => '沙瓤多汁，炒蛋或做沙律。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機青瓜',   'price' => 20.00, 'stock' => 45, 'is_organic' => 1, 'origin' => '上水古洞農場', 'carbon' => 0.200, 'image' => 'products/seed/cucumber.jpg',  'desc' => '清爽脆嫩，涼拌最開胃。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機甜粟米', 'price' => 24.00, 'stock' => 50, 'is_organic' => 1, 'origin' => '屯門農場',     'carbon' => 0.230, 'image' => 'products/seed/corn.jpg',      'desc' => '粒粒飽滿，蒸食或煲湯。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機薯仔',   'price' => 15.00, 'stock' => 70, 'is_organic' => 1, 'origin' => '打鼓嶺農場',   'carbon' => 0.160, 'image' => 'products/seed/potato.jpg',    'desc' => '粉糯香甜，咖喱、燜煮百搭。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機番薯',   'price' => 18.00, 'stock' => 55, 'is_organic' => 1, 'origin' => '西貢北潭涌',   'carbon' => 0.210, 'image' => 'products/seed/potato.jpg',    'desc' => '烤食流糖心，粗糧首選。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機洋蔥',   'price' => 14.00, 'stock' => 65, 'is_organic' => 1, 'origin' => '元朗八鄉農場', 'carbon' => 0.150, 'image' => 'products/seed/onion.jpg',     'desc' => '甜辣適中，炒菜提味必備。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機蒜頭',   'price' => 22.00, 'stock' => 40, 'is_organic' => 1, 'origin' => '粉嶺鶴藪農場', 'carbon' => 0.190, 'image' => 'products/seed/garlic.jpg',    'desc' => '蒜香濃烈，爆鍋靈魂。'],
            ['cat' => 'root-vegetables', 'name' => '本地老薑',       'price' => 26.00, 'stock' => 35, 'is_organic' => 0, 'origin' => '大埔林村',     'carbon' => 0.220, 'image' => 'products/seed/ginger.jpg',    'desc' => '辛味足，蒸魚、薑醋必用。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機南瓜',   'price' => 28.00, 'stock' => 30, 'is_organic' => 1, 'origin' => '西貢北潭涌',   'carbon' => 0.260, 'image' => 'products/seed/pumpkin.jpg',   'desc' => '粉甜綿密，蒸食或做濃湯。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機茄子',   'price' => 24.00, 'stock' => 32, 'is_organic' => 1, 'origin' => '元朗新田農場', 'carbon' => 0.240, 'image' => 'products/seed/eggplant.jpg',  'desc' => '肉質細滑，魚香茄子煲首選。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機青椒',   'price' => 26.00, 'stock' => 36, 'is_organic' => 1, 'origin' => '上水古洞農場', 'carbon' => 0.230, 'image' => 'products/seed/bellpepper.jpg', 'desc' => '脆甜多汁，炒牛肉經典配搭。'],
            ['cat' => 'root-vegetables', 'name' => '本地鮮冬菇',     'price' => 35.00, 'stock' => 25, 'is_organic' => 0, 'origin' => '大埔林村',     'carbon' => 0.350, 'image' => 'products/seed/mushroom.jpg',  'desc' => '肉厚鮮香，炒菜煲湯皆宜。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機蘆筍',   'price' => 38.00, 'stock' => 20, 'is_organic' => 1, 'origin' => '打鼓嶺農場',   'carbon' => 0.320, 'image' => 'products/seed/mixedveg.jpg',  'desc' => '嫩甜爽脆，白灼配橄欖油。'],
            ['cat' => 'root-vegetables', 'name' => '本地有機紅菜頭', 'price' => 28.00, 'stock' => 22, 'is_organic' => 1, 'origin' => '粉嶺鶴藪農場', 'carbon' => 0.270, 'image' => 'products/seed/carrot.jpg',    'desc' => '色澤鮮艷，沙律或羅宋湯。'],

            // ===== 時令水果 seasonal (18) =====
            ['cat' => 'seasonal', 'name' => '本地有機蘋果',   'price' => 38.00, 'stock' => 40, 'is_organic' => 1, 'origin' => '北區蓮麻坑',   'carbon' => 0.380, 'image' => 'products/seed/apple.jpg',       'desc' => '爽脆清甜，每日一蘋果。'],
            ['cat' => 'seasonal', 'name' => '本地香蕉',       'price' => 18.00, 'stock' => 50, 'is_organic' => 0, 'origin' => '元朗流浮山',   'carbon' => 0.320, 'image' => 'products/seed/banana.jpg',      'desc' => '自然熟成，軟糯香甜。'],
            ['cat' => 'seasonal', 'name' => '本地有機草莓',   'price' => 58.00, 'stock' => 20, 'is_organic' => 1, 'origin' => '大埔大埔滘',   'carbon' => 0.450, 'image' => 'products/seed/strawberry.jpg',  'desc' => '冬季限定，甜度高汁水足。'],
            ['cat' => 'seasonal', 'name' => '本地有機藍莓',   'price' => 68.00, 'stock' => 15, 'is_organic' => 1, 'origin' => '沙頭角農場',   'carbon' => 0.480, 'image' => 'products/seed/blueberry.jpg',   'desc' => '抗氧化之王，乳酪好拍檔。'],
            ['cat' => 'seasonal', 'name' => '本地有機提子',   'price' => 48.00, 'stock' => 25, 'is_organic' => 1, 'origin' => '八鄉農場',     'carbon' => 0.420, 'image' => 'products/seed/grapes.jpg',      'desc' => '皮薄無籽，冰鎮更佳。'],
            ['cat' => 'seasonal', 'name' => '本地芒果',       'price' => 42.00, 'stock' => 30, 'is_organic' => 0, 'origin' => '元朗流浮山',   'carbon' => 0.400, 'image' => 'products/seed/mango.jpg',       'desc' => '香濃多汁，做甜品一流。'],
            ['cat' => 'seasonal', 'name' => '本地西瓜',       'price' => 35.00, 'stock' => 18, 'is_organic' => 0, 'origin' => '上水古洞',     'carbon' => 0.360, 'image' => 'products/seed/watermelon.jpg',  'desc' => '沙脆清甜，夏日消暑必備。'],
            ['cat' => 'seasonal', 'name' => '本地菠蘿',       'price' => 32.00, 'stock' => 22, 'is_organic' => 0, 'origin' => '大埔林村',     'carbon' => 0.380, 'image' => 'products/seed/pineapple.jpg',   'desc' => '酸甜多汁，咕嚕肉靈魂。'],
            ['cat' => 'seasonal', 'name' => '本地水蜜桃',     'price' => 45.00, 'stock' => 20, 'is_organic' => 1, 'origin' => '北區蓮麻坑',   'carbon' => 0.410, 'image' => 'products/seed/peach.jpg',       'desc' => '軟熟多汁，香氣撲鼻。'],
            ['cat' => 'seasonal', 'name' => '本地香梨',       'price' => 30.00, 'stock' => 35, 'is_organic' => 0, 'origin' => '沙田瀝源',     'carbon' => 0.340, 'image' => 'products/seed/pear.jpg',        'desc' => '清熱潤肺，燉湯亦可。'],
            ['cat' => 'seasonal', 'name' => '本地奇異果',     'price' => 36.00, 'stock' => 28, 'is_organic' => 1, 'origin' => '大埔大埔滘',   'carbon' => 0.370, 'image' => 'products/seed/kiwi.jpg',        'desc' => '維 C 爆錶，酸甜開胃。'],
            ['cat' => 'seasonal', 'name' => '本地火龍果',     'price' => 28.00, 'stock' => 25, 'is_organic' => 1, 'origin' => '元朗流浮山',   'carbon' => 0.350, 'image' => 'products/seed/dragonfruit.jpg', 'desc' => '紅肉清甜，顏值擔當。'],
            ['cat' => 'seasonal', 'name' => '本地牛油果',     'price' => 40.00, 'stock' => 22, 'is_organic' => 0, 'origin' => '西貢北潭涌',   'carbon' => 0.520, 'image' => 'products/seed/avocado.jpg',     'desc' => '綿滑高脂，多士沙律百搭。'],
            ['cat' => 'seasonal', 'name' => '本地車厘子',     'price' => 88.00, 'stock' => 12, 'is_organic' => 1, 'origin' => '沙頭角農場',   'carbon' => 0.550, 'image' => 'products/seed/cherry.jpg',      'desc' => '粒粒脆甜，送禮體面。'],
            ['cat' => 'seasonal', 'name' => '本地番石榴',     'price' => 25.00, 'stock' => 30, 'is_organic' => 0, 'origin' => '粉嶺鶴藪',     'carbon' => 0.300, 'image' => 'products/seed/guava.jpg',       'desc' => '爽脆清香，話梅粉絕配。'],
            ['cat' => 'seasonal', 'name' => '本地荔枝',       'price' => 45.00, 'stock' => 16, 'is_organic' => 0, 'origin' => '大埔林村',     'carbon' => 0.430, 'image' => 'products/seed/peach.jpg',       'desc' => '初夏限定，清甜多汁。'],
            ['cat' => 'seasonal', 'name' => '本地椰子',       'price' => 30.00, 'stock' => 20, 'is_organic' => 0, 'origin' => '長洲',         'carbon' => 0.460, 'image' => 'products/seed/coconut.jpg',     'desc' => '椰水清甜，椰肉燉湯。'],
            ['cat' => 'seasonal', 'name' => '本地石榴',       'price' => 42.00, 'stock' => 15, 'is_organic' => 1, 'origin' => '北區蓮麻坑',   'carbon' => 0.440, 'image' => 'products/seed/pomegranate.jpg', 'desc' => '籽粒晶瑩，抗氧化佳品。'],

            // ===== 柑橘 citrus (6) =====
            ['cat' => 'citrus', 'name' => '本地有機檸檬', 'price' => 22.00, 'stock' => 45, 'is_organic' => 1, 'origin' => '長洲',         'carbon' => 0.260, 'image' => 'products/seed/lemon.jpg',      'desc' => '酸香醒胃，蜜漬或入饌。'],
            ['cat' => 'citrus', 'name' => '本地有機蜜柑', 'price' => 28.00, 'stock' => 38, 'is_organic' => 1, 'origin' => '北區蓮麻坑',   'carbon' => 0.330, 'image' => 'products/seed/orange.jpg',     'desc' => '皮薄易剝，甜度高。'],
            ['cat' => 'citrus', 'name' => '本地有機西柚', 'price' => 30.00, 'stock' => 25, 'is_organic' => 1, 'origin' => '沙田瀝源',     'carbon' => 0.350, 'image' => 'products/seed/grapefruit.jpg', 'desc' => '微苦回甘，沙律或榨汁。'],
            ['cat' => 'citrus', 'name' => '本地有機青橘', 'price' => 26.00, 'stock' => 30, 'is_organic' => 1, 'origin' => '大埔大埔滘',   'carbon' => 0.310, 'image' => 'products/seed/orange.jpg',     'desc' => '清香微酸，泡茶佳品。'],
            ['cat' => 'citrus', 'name' => '本地有機甜橙', 'price' => 32.00, 'stock' => 35, 'is_organic' => 1, 'origin' => '元朗八鄉',     'carbon' => 0.340, 'image' => 'products/seed/orange.jpg',     'desc' => '汁多味甜，鮮榨首選。'],
            ['cat' => 'citrus', 'name' => '本地有機年桔', 'price' => 26.00, 'stock' => 28, 'is_organic' => 1, 'origin' => '粉嶺鶴藪',     'carbon' => 0.300, 'image' => 'products/seed/orange.jpg',     'desc' => '鹹柑橘原材料，潤喉良品。'],

            // ===== 雞蛋 eggs (5) =====
            ['cat' => 'eggs', 'name' => '本地走地雞蛋（15 隻）', 'price' => 88.00, 'stock' => 30, 'is_organic' => 1, 'origin' => '元朗雞場',   'carbon' => 1.450, 'image' => 'products/seed/eggs2.jpg', 'desc' => '家庭裝更抵食，蛋香濃郁。'],
            ['cat' => 'eggs', 'name' => '本地有機雞蛋（10 隻）', 'price' => 55.00, 'stock' => 40, 'is_organic' => 1, 'origin' => '打鼓嶺',     'carbon' => 1.220, 'image' => 'products/seed/eggs.jpg',  'desc' => '有機飼料餵飼，安心之選。'],
            ['cat' => 'eggs', 'name' => '本地初生蛋（10 隻）',   'price' => 68.00, 'stock' => 25, 'is_organic' => 1, 'origin' => '粉嶺鶴藪',   'carbon' => 1.280, 'image' => 'products/seed/eggs2.jpg', 'desc' => '蛋味極濃，蒸水蛋首選。'],
            ['cat' => 'eggs', 'name' => '本地鴨蛋（6 隻）',       'price' => 38.00, 'stock' => 20, 'is_organic' => 0, 'origin' => '元朗流浮山', 'carbon' => 1.350, 'image' => 'products/seed/eggs.jpg',  'desc' => '做鹹蛋、皮蛋上佳材料。'],
            ['cat' => 'eggs', 'name' => '本地鵪鶉蛋（12 隻）',   'price' => 32.00, 'stock' => 22, 'is_organic' => 0, 'origin' => '大埔林村',   'carbon' => 1.100, 'image' => 'products/seed/eggs2.jpg', 'desc' => '小巧入味，鹵水、火鍋皆宜。'],

            // ===== 鮮肉 fresh-meat (9) =====
            ['cat' => 'fresh-meat', 'name' => '本地走地雞（半隻）', 'price' => 98.00,  'stock' => 12, 'is_organic' => 0, 'origin' => '元朗雞場',     'carbon' => 4.200, 'image' => 'products/seed/chicken.jpg',       'desc' => '放養 120 日，皮爽肉滑。'],
            ['cat' => 'fresh-meat', 'name' => '本地有機雞胸肉',     'price' => 65.00,  'stock' => 20, 'is_organic' => 1, 'origin' => '打鼓嶺',       'carbon' => 3.800, 'image' => 'products/seed/chickenbreast.jpg', 'desc' => '低脂高蛋白，健身餐必備。'],
            ['cat' => 'fresh-meat', 'name' => '本地有機牛肉',       'price' => 128.00, 'stock' => 15, 'is_organic' => 1, 'origin' => '上水屠房直送', 'carbon' => 5.800, 'image' => 'products/seed/beef.jpg',           'desc' => '新鮮劏切，炒煮皆宜。'],
            ['cat' => 'fresh-meat', 'name' => '本地手切牛扒',       'price' => 158.00, 'stock' => 10, 'is_organic' => 0, 'origin' => '上水屠房直送', 'carbon' => 6.200, 'image' => 'products/seed/beef2.jpg',          'desc' => '厚切肉嫩，香煎最佳。'],
            ['cat' => 'fresh-meat', 'name' => '本地有機豬肉',       'price' => 88.00,  'stock' => 18, 'is_organic' => 1, 'origin' => '上水屠房直送', 'carbon' => 4.500, 'image' => 'products/seed/pork.jpg',           'desc' => '肉味濃郁，無激素。'],
            ['cat' => 'fresh-meat', 'name' => '本地豬腩肉',         'price' => 78.00,  'stock' => 16, 'is_organic' => 0, 'origin' => '上水屠房直送', 'carbon' => 4.800, 'image' => 'products/seed/pork.jpg',           'desc' => '五花分明，紅燒肉指定部位。'],
            ['cat' => 'fresh-meat', 'name' => '本地雞翼',           'price' => 58.00,  'stock' => 25, 'is_organic' => 0, 'origin' => '元朗雞場',     'carbon' => 3.600, 'image' => 'products/seed/chicken.jpg',       'desc' => '肉滑骨香，豉油王、蜜汁皆可。'],
            ['cat' => 'fresh-meat', 'name' => '本地免治牛肉',       'price' => 75.00,  'stock' => 14, 'is_organic' => 0, 'origin' => '上水屠房直送', 'carbon' => 5.500, 'image' => 'products/seed/beef.jpg',           'desc' => '即日絞製，漢堡、肉醬意粉用。'],
            ['cat' => 'fresh-meat', 'name' => '本地雞髀',           'price' => 55.00,  'stock' => 22, 'is_organic' => 0, 'origin' => '元朗雞場',     'carbon' => 3.900, 'image' => 'products/seed/chickenbreast.jpg', 'desc' => '肉厚多汁，燒焗一流。'],

            // ===== 鮮魚 fresh-fish (11) =====
            ['cat' => 'fresh-fish', 'name' => '本地新鮮三文魚', 'price' => 98.00,  'stock' => 15, 'is_organic' => 0, 'origin' => '香港仔魚市場',     'carbon' => 3.200, 'image' => 'products/seed/salmon.jpg',  'desc' => '刺身級別，香煎亦佳。'],
            ['cat' => 'fresh-fish', 'name' => '本地新鮮鯛魚',   'price' => 75.00,  'stock' => 10, 'is_organic' => 0, 'origin' => '西貢漁民直送',     'carbon' => 2.400, 'image' => 'products/seed/fish.jpg',    'desc' => '肉質細嫩，清蒸顯鮮。'],
            ['cat' => 'fresh-fish', 'name' => '本地新鮮鱸魚',   'price' => 85.00,  'stock' => 12, 'is_organic' => 0, 'origin' => '南丫島',           'carbon' => 2.600, 'image' => 'products/seed/fish2.jpg',   'desc' => '少骨味鮮，老少皆宜。'],
            ['cat' => 'fresh-fish', 'name' => '本地新鮮鮮魷',   'price' => 65.00,  'stock' => 14, 'is_organic' => 0, 'origin' => '長洲漁港',         'carbon' => 2.200, 'image' => 'products/seed/squid.jpg',   'desc' => '爽脆彈牙，白灼或爆炒。'],
            ['cat' => 'fresh-fish', 'name' => '本地帶子',       'price' => 128.00, 'stock' => 10, 'is_organic' => 0, 'origin' => '流浮山漁排',       'carbon' => 3.800, 'image' => 'products/seed/scallop.jpg', 'desc' => '粒大鮮甜，粉絲蒸最經典。'],
            ['cat' => 'fresh-fish', 'name' => '本地新鮮大蝦',   'price' => 95.00,  'stock' => 16, 'is_organic' => 0, 'origin' => '屯門三聖邨',       'carbon' => 4.000, 'image' => 'products/seed/shrimp2.jpg', 'desc' => '生猛大隻，白灼見真味。'],
            ['cat' => 'fresh-fish', 'name' => '本地鯇魚',       'price' => 68.00,  'stock' => 11, 'is_organic' => 0, 'origin' => '西貢漁民直送',     'carbon' => 2.100, 'image' => 'products/seed/fish.jpg',    'desc' => '起片打邊爐，鮮甜滑嫩。'],
            ['cat' => 'fresh-fish', 'name' => '本地鯖魚',       'price' => 55.00,  'stock' => 13, 'is_organic' => 0, 'origin' => '香港仔魚市場',     'carbon' => 1.900, 'image' => 'products/seed/fish2.jpg',   'desc' => '油脂豐腴，鹽燒最惹味。'],
            ['cat' => 'fresh-fish', 'name' => '本地墨魚',       'price' => 72.00,  'stock' => 9,  'is_organic' => 0, 'origin' => '南丫島',           'carbon' => 2.500, 'image' => 'products/seed/squid.jpg',   'desc' => '肉厚爽滑，釀墨魚筒特色菜。'],
            ['cat' => 'fresh-fish', 'name' => '本地黃沙蜆',     'price' => 45.00,  'stock' => 20, 'is_organic' => 0, 'origin' => '流浮山漁排',       'carbon' => 1.600, 'image' => 'products/seed/shrimp.jpg',  'desc' => '吐沙乾淨，豉椒炒送飯。'],
            ['cat' => 'fresh-fish', 'name' => '本地新鮮黃花魚', 'price' => 78.00,  'stock' => 10, 'is_organic' => 0, 'origin' => '長洲漁港',         'carbon' => 2.800, 'image' => 'products/seed/fish.jpg',    'desc' => '蒜瓣肉質，清蒸鮮甜。'],

            // ===== 米麵 rice-noodle (12) =====
            ['cat' => 'rice-noodle', 'name' => '本地有機糙米 2kg',   'price' => 58.00, 'stock' => 35, 'is_organic' => 1, 'origin' => '元朗新田',     'carbon' => 1.100, 'image' => 'products/seed/rice.jpg',       'desc' => '高纖低 GI，健康主食。'],
            ['cat' => 'rice-noodle', 'name' => '本地有機白米 5kg',   'price' => 88.00, 'stock' => 25, 'is_organic' => 1, 'origin' => '元朗新田',     'carbon' => 1.250, 'image' => 'products/seed/ricebowl.jpg',   'desc' => '家庭裝，飯香軟糯。'],
            ['cat' => 'rice-noodle', 'name' => '本地手工河粉',       'price' => 22.00, 'stock' => 40, 'is_organic' => 0, 'origin' => '大埔',         'carbon' => 0.850, 'image' => 'products/seed/ricenoodle.jpg', 'desc' => '滑溜爽口，乾炒牛河靈魂。'],
            ['cat' => 'rice-noodle', 'name' => '本地手工伊麵',       'price' => 28.00, 'stock' => 30, 'is_organic' => 0, 'origin' => '長洲老字號',   'carbon' => 0.900, 'image' => 'products/seed/noodles.jpg',    'desc' => '蛋香濃郁，壽宴必備。'],
            ['cat' => 'rice-noodle', 'name' => '本地有機全麥麵',     'price' => 32.00, 'stock' => 28, 'is_organic' => 1, 'origin' => '元朗',         'carbon' => 0.950, 'image' => 'products/seed/pasta2.jpg',     'desc' => '高纖彈牙，健康之選。'],
            ['cat' => 'rice-noodle', 'name' => '本地即食米粉',       'price' => 18.00, 'stock' => 50, 'is_organic' => 0, 'origin' => '大埔',         'carbon' => 0.800, 'image' => 'products/seed/ricenoodle.jpg', 'desc' => '三分鐘快熟，夜宵恩物。'],
            ['cat' => 'rice-noodle', 'name' => '本地有機意粉',       'price' => 35.00, 'stock' => 32, 'is_organic' => 1, 'origin' => '元朗',         'carbon' => 0.980, 'image' => 'products/seed/pasta.jpg',      'desc' => '杜蘭小麥，掛汁力強。'],
            ['cat' => 'rice-noodle', 'name' => '本地手工拉麵',       'price' => 30.00, 'stock' => 26, 'is_organic' => 0, 'origin' => '九龍城',       'carbon' => 0.920, 'image' => 'products/seed/ramen.jpg',      'desc' => '每日現拉，湯麵拌麵皆可。'],
            ['cat' => 'rice-noodle', 'name' => '本地全麥方包',       'price' => 25.00, 'stock' => 20, 'is_organic' => 0, 'origin' => '觀塘麵包廠',   'carbon' => 0.750, 'image' => 'products/seed/bread.jpg',      'desc' => '每日新鮮出爐，早餐必備。'],
            ['cat' => 'rice-noodle', 'name' => '本地有機燕麥',       'price' => 42.00, 'stock' => 30, 'is_organic' => 1, 'origin' => '元朗',         'carbon' => 0.700, 'image' => 'products/seed/rice.jpg',       'desc' => '降膽固醇，早餐乳酪好拍檔。'],
            ['cat' => 'rice-noodle', 'name' => '本地有機糯米',       'price' => 45.00, 'stock' => 22, 'is_organic' => 1, 'origin' => '元朗新田',     'carbon' => 1.150, 'image' => 'products/seed/ricebowl.jpg',   'desc' => '做糭、糯米飯必用。'],
            ['cat' => 'rice-noodle', 'name' => '本地有機豆腐 6 盒裝', 'price' => 65.00, 'stock' => 25, 'is_organic' => 1, 'origin' => '元朗豆腐廠',   'carbon' => 0.720, 'image' => 'products/seed/tofu.jpg',       'desc' => '家庭裝更抵，即日新鮮製。'],

            // ===== 醬料 sauces (14) =====
            ['cat' => 'sauces', 'name' => '本地有機豉油',   'price' => 38.00,  'stock' => 30, 'is_organic' => 1, 'origin' => '九龍城老醬園', 'carbon' => 0.480, 'image' => 'products/seed/soysauce.jpg', 'desc' => '有機黃豆釀造，鮮味天然。'],
            ['cat' => 'sauces', 'name' => '本地冷壓橄欖油', 'price' => 88.00,  'stock' => 20, 'is_organic' => 1, 'origin' => '上環進口商',   'carbon' => 0.850, 'image' => 'products/seed/oliveoil.jpg', 'desc' => '初榨冷壓，沙律、低溫煮食用。'],
            ['cat' => 'sauces', 'name' => '本地百花蜜',     'price' => 68.00,  'stock' => 18, 'is_organic' => 1, 'origin' => '沙頭角蜂農',   'carbon' => 0.360, 'image' => 'products/seed/honey.jpg',    'desc' => '山花釀造，潤喉養顏。'],
            ['cat' => 'sauces', 'name' => '本地有機香料粉', 'price' => 32.00,  'stock' => 25, 'is_organic' => 1, 'origin' => '九龍城',       'carbon' => 0.420, 'image' => 'products/seed/spices.jpg',   'desc' => '複合香料，醃肉提香。'],
            ['cat' => 'sauces', 'name' => '本地五香粉',     'price' => 25.00,  'stock' => 30, 'is_organic' => 0, 'origin' => '九龍城',       'carbon' => 0.400, 'image' => 'products/seed/spices2.jpg',  'desc' => '滷水、燒肉必備。'],
            ['cat' => 'sauces', 'name' => '本地有機香草',   'price' => 28.00,  'stock' => 22, 'is_organic' => 1, 'origin' => '大埔林村',     'carbon' => 0.380, 'image' => 'products/seed/herbs.jpg',    'desc' => '新鮮香草，西餐點睛。'],
            ['cat' => 'sauces', 'name' => '本地豆瓣醬',     'price' => 30.00,  'stock' => 28, 'is_organic' => 0, 'origin' => '九龍城老醬園', 'carbon' => 0.500, 'image' => 'products/seed/sauce.jpg',    'desc' => '川菜靈魂，麻婆豆腐必用。'],
            ['cat' => 'sauces', 'name' => '本地黃薑粉',     'price' => 35.00,  'stock' => 20, 'is_organic' => 1, 'origin' => '大埔林村',     'carbon' => 0.440, 'image' => 'products/seed/turmeric.jpg', 'desc' => '咖喱底色，抗氧化佳品。'],
            ['cat' => 'sauces', 'name' => '本地辣椒醬',     'price' => 32.00,  'stock' => 26, 'is_organic' => 0, 'origin' => '九龍城老醬園', 'carbon' => 0.460, 'image' => 'products/seed/chili.jpg',    'desc' => '香辣過癮，蘸餃子一流。'],
            ['cat' => 'sauces', 'name' => '本地指天椒',     'price' => 20.00,  'stock' => 30, 'is_organic' => 0, 'origin' => '元朗八鄉',     'carbon' => 0.280, 'image' => 'products/seed/chili2.jpg',   'desc' => '勁辣鮮香，小炒提味。'],
            ['cat' => 'sauces', 'name' => '本地米醋',       'price' => 28.00,  'stock' => 24, 'is_organic' => 0, 'origin' => '九龍城老醬園', 'carbon' => 0.420, 'image' => 'products/seed/vinegar.jpg',  'desc' => '酸香柔和，涼拌、點餃子。'],
            ['cat' => 'sauces', 'name' => '本地特級XO醬',   'price' => 128.00, 'stock' => 12, 'is_organic' => 0, 'origin' => '上環老牌醬廠', 'carbon' => 0.980, 'image' => 'products/seed/sauce.jpg',    'desc' => '足料瑤柱金華火腿，送禮之選。'],
            ['cat' => 'sauces', 'name' => '本地麻油',       'price' => 45.00,  'stock' => 22, 'is_organic' => 0, 'origin' => '九龍城老醬園', 'carbon' => 0.520, 'image' => 'products/seed/oliveoil.jpg', 'desc' => '石磨小磨麻油，香氣四溢。'],
            ['cat' => 'sauces', 'name' => '本地有機花生醬', 'price' => 40.00,  'stock' => 20, 'is_organic' => 1, 'origin' => '觀塘食品廠',   'carbon' => 0.580, 'image' => 'products/seed/sauce.jpg',    'desc' => '無添加糖鹽，多士好拍檔。'],
        ];

        $count = 0;
        foreach ($products as $p) {
            Product::updateOrCreate(
                ['name' => $p['name']],
                [
                    'name' => $p['name'],
                    'description' => $p['desc'],
                    'price' => $p['price'],
                    'image' => $p['image'],
                    'carbon_footprint' => $p['carbon'],
                    'stock' => $p['stock'],
                    'category_id' => $subModels[$p['cat']]->id ?? null,
                    'is_organic' => (bool) $p['is_organic'],
                    'origin' => $p['origin'],
                    'status' => Product::STATUS_PUBLISHED,
                ],
            );
            $count++;
        }
        $this->command->info("  ✓ Extra products: {$count}");
    }
}
