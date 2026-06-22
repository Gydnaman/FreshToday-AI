<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * 为 products 表加 status 字段（draft / published / archived），
     * 支持 admin 后台上架工作流。
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('status', 16)->default('published')->after('stock')->index();
            // 老数据（已 seed 的产品）默认 published，admin 新建的默认 draft
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
