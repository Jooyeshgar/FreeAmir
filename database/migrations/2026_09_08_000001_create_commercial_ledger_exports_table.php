<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_ledger_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('format', 10);
            $table->string('seal_tracking_code', 100);
            $table->string('ledger_type', 50);
            $table->string('status', 30)->default('ready');
            $table->string('file_path');
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamps();
        });

        foreach ([
            'commercial-ledgers.index',
            'commercial-ledgers.store',
            'commercial-ledgers.show',
            'commercial-ledgers.download',
            'commercial-ledgers.destroy',
        ] as $permission) {
            DB::table(config('permission.table_names.permissions'))->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))->whereIn('name', [
            'commercial-ledgers.index',
            'commercial-ledgers.store',
            'commercial-ledgers.show',
            'commercial-ledgers.download',
            'commercial-ledgers.destroy',
        ])->delete();

        Schema::dropIfExists('commercial_ledger_exports');
    }
};
