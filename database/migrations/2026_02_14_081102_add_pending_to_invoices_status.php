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
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'pre_invoice',
                'approved',
                'unapproved',
                'approved_inactive',
                'rejected',
                'ready_to_approve',
            ])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('invoices')->where('status', 'pending')->update(['status' => 'pre_invoice']);

        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', [
                'pre_invoice',
                'approved',
                'unapproved',
                'approved_inactive',
                'rejected',
                'ready_to_approve',
            ])->default('pre_invoice')->change();
        });
    }
};
