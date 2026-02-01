<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('cogs_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
        });

        Schema::table('service_groups', function (Blueprint $table) {
            $table->foreignId('cogs_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cogs_subject_id');
        });

        Schema::table('service_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cogs_subject_id');
        });
    }
};
