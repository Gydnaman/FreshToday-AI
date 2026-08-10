<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        foreach ($this->imageMap() as $name => [$oldImage, $newImage]) {
            DB::table('products')
                ->where('name', $name)
                ->where(function ($query) use ($oldImage): void {
                    $query->where('image', $oldImage)->orWhereNull('image');
                })
                ->update(['image' => $newImage]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        foreach ($this->imageMap() as $name => [$oldImage, $newImage]) {
            DB::table('products')
                ->where('name', $name)
                ->where('image', $newImage)
                ->update(['image' => $oldImage]);
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function imageMap(): array
    {
        return [
            '本地有機菜心' => ['https://placehold.co/400x400/4ade80/ffffff?text=%E8%8F%9C%E5%BF%83', 'images/products/seed/choysum.jpg'],
            '本地有機白菜' => ['https://placehold.co/400x400/4ade80/ffffff?text=%E7%99%BD%E8%8F%9C', 'images/products/seed/cabbage.jpg'],
            '本地西洋菜' => ['https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=400', 'images/products/seed/spinach.jpg'],
            '本地有機紅蘿蔔' => ['https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=400', 'images/products/seed/carrot.jpg'],
            '本地黃薑' => ['https://images.unsplash.com/photo-1615485290382-441e4d049cb5?w=400', 'images/products/seed/ginger.jpg'],
            '本地紫薯' => ['https://placehold.co/400x400/a855f7/ffffff?text=%E7%B4%AB%E8%96%AF', 'images/products/seed/potato.jpg'],
            '本地沙田柚' => ['https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?w=400', 'images/products/seed/grapefruit.jpg'],
            '本地楊桃' => ['https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?w=400', 'images/products/seed/guava.jpg'],
            '本地木瓜' => ['https://images.unsplash.com/photo-1611080626919-7cf5a9dbab5b?w=400', 'images/products/seed/papaya.jpg'],
            '本地有機臍橙' => ['https://images.unsplash.com/photo-1547514701-42782101795e?w=400', 'images/products/seed/orange.jpg'],
            '本地青檸' => ['https://images.unsplash.com/photo-1590502593747-42a996133562?w=400', 'images/products/seed/lemon.jpg'],
            '本地走地雞蛋（10 隻）' => ['https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?w=400', 'images/products/seed/eggs.jpg'],
            '本地初生蛋（6 隻）' => ['https://images.unsplash.com/photo-1569288063643-5d29ad6dfc8d?w=400', 'images/products/seed/eggs2.jpg'],
            '本地新鮮烏頭' => ['https://images.unsplash.com/photo-1559827260-dc66d52bef19?w=400', 'images/products/seed/fish.jpg'],
            '本地龍躉柳' => ['https://images.unsplash.com/photo-1565280654386-466c2a1a4a13?w=400', 'images/products/seed/fish2.jpg'],
            '本地蝦仁' => ['https://images.unsplash.com/photo-1565680018434-b513d5e5fd47?w=400', 'images/products/seed/shrimp.jpg'],
            '本地有機絲苗米 2kg' => ['https://images.unsplash.com/photo-1568347355280-d33fdf77d42a?w=400', 'images/products/seed/rice.jpg'],
            '本地蝦子麵' => ['https://images.unsplash.com/photo-1607330289024-1535c6b4e1c1?w=400', 'images/products/seed/noodles.jpg'],
            '本地米線' => ['https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400', 'images/products/seed/ricenoodle.jpg'],
            '本地手工豉油' => ['https://images.unsplash.com/photo-1599909366516-6c1f0fcaa0a5?w=400', 'images/products/seed/soysauce.jpg'],
            '本地XO醬' => ['https://images.unsplash.com/photo-1604908554027-6f2b16e5b8e3?w=400', 'images/products/seed/sauce.jpg'],
            '本地蜜糖（龍眼蜜）' => ['https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=400', 'images/products/seed/honey.jpg'],
            '本地有機豆腐 3 盒裝' => ['https://images.unsplash.com/photo-1620706857370-e1b9770e8bb1?w=400', 'images/products/seed/tofu.jpg'],
            '本地粟米' => ['https://images.unsplash.com/photo-1551754655-cd27e38d2076?w=400', 'images/products/seed/corn.jpg'],
        ];
    }
};
