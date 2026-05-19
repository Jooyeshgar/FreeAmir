<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payrolls MODIFY status ENUM('draft', 'pending_manager_approval', 'approved', 'paid') NOT NULL DEFAULT 'draft'");
        }

        Schema::create('payroll_status_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('payroll_id');
            $table->string('from_status', 50);
            $table->string('to_status', 50);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('payroll_id')
                ->references('id')->on('payrolls')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_status_histories');

        if (DB::getDriverName() === 'mysql') {
            DB::table('payrolls')
                ->where('status', 'pending_manager_approval')
                ->update(['status' => 'draft']);

            DB::statement("ALTER TABLE payrolls MODIFY status ENUM('draft', 'approved', 'paid') NOT NULL DEFAULT 'draft'");
        }
    }
};
