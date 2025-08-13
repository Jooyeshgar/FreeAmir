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
        Schema::table('product_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->after('sellId')->nullable();
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->after('name')->nullable();
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
        });
    }
};
