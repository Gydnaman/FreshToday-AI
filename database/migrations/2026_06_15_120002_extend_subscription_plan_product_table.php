<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-15 补：subscription_plan_product pivot 表加 price + quantity
 * - 详见 app/Models/SubscriptionPlan.php::products() 关系（withPivot('price','quantity')）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plan_product', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->after('product_id')->comment('快照价格');
            $table->unsignedInteger('quantity')->default(1)->after('price')->comment('每期份数');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plan_product', function (Blueprint $table) {
            $table->dropColumn(['price', 'quantity']);
        });
    }
};
