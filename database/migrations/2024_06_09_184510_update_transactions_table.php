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
        Schema::table('transactions',function (Blueprint $table){
            $table->double('debit')->change()->default(0);
            $table->double('credit')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions',function (Blueprint $table){
            $table->integer('debit')->change();
            $table->dropColumn('credit');
        });
    }
};
