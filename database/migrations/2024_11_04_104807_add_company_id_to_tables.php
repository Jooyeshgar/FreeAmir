<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->after('name');
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->after('status');
        });
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->after('name');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->after('invoice_id');
        });


        DB::table('banks')->update(['company_id' => 1]);
        DB::table('cheques')->update(['company_id' => 1]);
        DB::table('customer_groups')->update(['company_id' => 1]);
        DB::table('payments')->update(['company_id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
