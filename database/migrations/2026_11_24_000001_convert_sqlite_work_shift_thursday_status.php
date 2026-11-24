<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The enum-to-integer migration skips SQLite, so convert thursday_status here.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite' || $this->isInteger()) {
            return;
        }

        Schema::table('work_shifts', fn (Blueprint $table) => $table->unsignedTinyInteger('thursday_status_new')->default(3));

        DB::statement("UPDATE work_shifts SET thursday_status_new = CASE thursday_status WHEN 'holiday' THEN 1 WHEN 'full_day' THEN 2 ELSE 3 END");

        $this->replaceColumn();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite' || ! $this->isInteger()) {
            return;
        }

        Schema::table('work_shifts', fn (Blueprint $table) => $table->string('thursday_status_new')->default('half_day'));

        DB::statement("UPDATE work_shifts SET thursday_status_new = CASE thursday_status WHEN 1 THEN 'holiday' WHEN 2 THEN 'full_day' ELSE 'half_day' END");

        $this->replaceColumn();
    }

    private function isInteger(): bool
    {
        return Schema::getColumnType('work_shifts', 'thursday_status') === 'integer';
    }

    private function replaceColumn(): void
    {
        Schema::table('work_shifts', fn (Blueprint $table) => $table->dropColumn('thursday_status'));
        Schema::table('work_shifts', fn (Blueprint $table) => $table->renameColumn('thursday_status_new', 'thursday_status'));
    }
};
