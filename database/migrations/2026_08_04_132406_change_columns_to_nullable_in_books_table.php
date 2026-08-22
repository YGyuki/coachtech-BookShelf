<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // isbnとpublished_date(公開日)カラムをnullableに変更
            $table->string('isbn', 13)->nullable()->change();
            $table->date('published_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // ロールバック時は nullable を解除（必須カラムに戻す）
            $table->string('isbn', 13)->nullable(false)->change();
            $table->date('published_date')->nullable(false)->change();
        });
    }
};
