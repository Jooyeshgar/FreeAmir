<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
                'partially_paid',
                'paid',
            ])->default('pending')->change();
        });

        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'approved',
                'unapproved',
                'approved_inactive',
                'partially_paid',
                'paid',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('invoices')->whereIn('status', ['partially_paid', 'paid'])->update(['status' => 'approved']);
        DB::table('ancillary_costs')->whereIn('status', ['partially_paid', 'paid'])->update(['status' => 'approved']);

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

        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'approved',
                'unapproved',
                'approved_inactive',
            ])->default('pending')->change();
        });
    }
};
