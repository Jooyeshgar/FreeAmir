<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['type_buyer', 'type_seller', 'type_mate', 'type_agent']);

            $table->enum('type', ['individual', 'legal_entity', 'civil_partnership', 'foreign_national'])->default('individual');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('type');

            $table->boolean('type_buyer')->default(false);
            $table->boolean('type_seller')->default(false);
            $table->boolean('type_mate')->default(false);
            $table->boolean('type_agent')->default(false);
        });
    }
};
