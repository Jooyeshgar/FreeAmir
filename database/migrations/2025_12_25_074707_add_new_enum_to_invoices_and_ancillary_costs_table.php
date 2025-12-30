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
            $table->enum('status', ['pending', 'approved', 'unapproved', 'approved_inactive'])
                ->default('pending')
                ->change();
        });

        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'unapproved', 'approved_inactive'])
                ->default('pending')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'unapproved'])
                ->default('pending')
                ->after('invoice_type')
                ->change();
        });

        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'unapproved'])
                ->default('pending')
                ->change();
        });
    }
};
