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
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->after('description')->nullable();
            $table->dropColumn('code');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {

            $table->string('code', 20)->unique()->after('id');
            $table->dropForeign(['subject_id']);
            $table->dropColumn('subject_id');
        });
    }
};
