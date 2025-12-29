<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->unsignedBigInteger('number')->nullable()->after('id');
        });

        DB::table('ancillary_costs')->whereNull('number')->update([
            'number' => DB::raw('id'),
        ]);

        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->unique('number');
        });
    }

    public function down(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });
    }
};
