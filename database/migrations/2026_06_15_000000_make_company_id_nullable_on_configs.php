<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
            $table->text('value')->nullable(false)->change();
        });
    }
};
