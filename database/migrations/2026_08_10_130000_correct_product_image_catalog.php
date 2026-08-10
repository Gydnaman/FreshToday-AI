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

        foreach ($this->pathMap() as $oldPath => $newPath) {
            DB::table('products')
                ->where('image', $oldPath)
                ->update(['image' => $newPath]);
        }

        foreach ($this->productOverrides() as $name => $newPath) {
            DB::table('products')
                ->where('name', $name)
                ->update(['image' => $newPath]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        foreach ($this->pathMap() as $oldPath => $newPath) {
            DB::table('products')
                ->where('image', $newPath)
                ->update(['image' => $oldPath]);
        }

        foreach ($this->overrideRollbackMap() as $name => $oldPath) {
            DB::table('products')
                ->where('name', $name)
                ->update(['image' => $oldPath]);
        }
    }

    /** @return array<string, string> */
    private function pathMap(): array
    {
        return [
            'images/products/seed/choysum.jpg' => 'images/products/catalog-v2/choysum.jpg',
            'images/products/seed/cabbage.jpg' => 'images/products/catalog-v2/napa-cabbage.jpg',
            'images/products/seed/ginger.jpg' => 'images/products/catalog-v2/ginger.jpg',
            'images/products/seed/grapefruit.jpg' => 'images/products/catalog-v2/pomelo.jpg',
            'images/products/seed/eggs.jpg' => 'images/products/catalog-v2/eggs.jpg',
            'images/products/seed/fish.jpg' => 'images/products/catalog-v2/mullet.jpg',
            'images/products/seed/soysauce.jpg' => 'images/products/catalog-v2/soysauce.jpg',
            'images/products/seed/sauce.jpg' => 'images/products/catalog-v2/xo-sauce.jpg',
            'images/products/seed/honey.jpg' => 'images/products/catalog-v2/honey.jpg',
            'images/products/seed/tofu.jpg' => 'images/products/catalog-v2/tofu.jpg',
            'images/products/seed/coconut.jpg' => 'images/products/catalog-v2/coconut.jpg',
            'images/products/seed/mushroom.jpg' => 'images/products/catalog-v2/mushroom.jpg',
            'images/products/seed/pumpkin.jpg' => 'images/products/catalog-v2/pumpkin.jpg',
            'images/products/seed/scallop.jpg' => 'images/products/catalog-v2/scallop.jpg',
            'images/products/seed/radish.jpg' => 'images/products/catalog-v2/daikon.jpg',
            'images/products/seed/chickenbreast.jpg' => 'images/products/catalog-v2/chicken-breast.jpg',
            'images/products/seed/chili.jpg' => 'images/products/catalog-v2/chili-sauce.jpg',
        ];
    }

    /** @return array<string, string> */
    private function productOverrides(): array
    {
        return [
            '本地紫薯' => 'images/products/catalog-v2/purple-sweet-potato.jpg',
            '本地有機番薯' => 'images/products/catalog-v2/purple-sweet-potato.jpg',
            '本地楊桃' => 'images/products/catalog-v2/starfruit.jpg',
            '本地青檸' => 'images/products/catalog-v2/lime.jpg',
            '本地荔枝' => 'images/products/catalog-v2/lychee.jpg',
            '本地辣椒醬' => 'images/products/catalog-v2/chili-sauce.jpg',
            '本地有機花生醬' => 'images/products/catalog-v2/peanut-butter.jpg',
        ];
    }

    /** @return array<string, string> */
    private function overrideRollbackMap(): array
    {
        return [
            '本地紫薯' => 'images/products/seed/potato.jpg',
            '本地有機番薯' => 'images/products/seed/potato.jpg',
            '本地楊桃' => 'images/products/seed/guava.jpg',
            '本地青檸' => 'images/products/seed/lemon.jpg',
            '本地荔枝' => 'images/products/seed/peach.jpg',
            '本地辣椒醬' => 'images/products/seed/chili.jpg',
            '本地有機花生醬' => 'images/products/seed/sauce.jpg',
        ];
    }
};
