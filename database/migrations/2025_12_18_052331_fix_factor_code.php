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
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('code', 20)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('code')->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->integer('code')->change();
        });
    }
};
