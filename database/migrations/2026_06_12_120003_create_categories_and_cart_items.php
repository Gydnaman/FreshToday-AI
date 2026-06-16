<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 1: 新建 categories / cart_items 表
 * 详见 docs/bmad/er-diagram.md §2.2 / §2.11
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('parent_id');
        });

        // 补 products.category_id 外键（原本是 unsignedBigInteger nullable）
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->boolean('is_organic')->default(1)->after('stock');
            $table->string('origin', 100)->nullable()->after('is_organic')->comment('香港本地农场');
            $table->index(['is_organic', 'category_id']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['is_organic', 'category_id']);
            $table->dropColumn(['is_organic', 'origin']);
        });
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('categories');
    }
};
