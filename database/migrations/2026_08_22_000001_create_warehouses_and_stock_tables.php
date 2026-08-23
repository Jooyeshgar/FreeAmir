<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'code']);
        });

        Schema::create('warehouse_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 2)->default(0);
            $table->decimal('average_cost', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            $warehouseId = DB::table('warehouses')->insertGetId([
                'company_id' => $companyId,
                'name' => 'انبار اصلی',
                'code' => 'MAIN',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')->where('company_id', $companyId)->update(['warehouse_id' => $warehouseId]);

            foreach (DB::table('products')->where('company_id', $companyId)->get(['id', 'quantity', 'average_cost']) as $product) {
                DB::table('warehouse_product_stocks')->insert([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $product->id,
                    'quantity' => $product->quantity ?? 0,
                    'average_cost' => $product->average_cost ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('invoice_items')->join('products', function ($join) use ($companyId) {
                $join->on('products.id', '=', 'invoice_items.itemable_id')
                    ->where('invoice_items.itemable_type', '=', 'App\\Models\\Product')
                    ->where('products.company_id', '=', $companyId);
            })->whereNull('invoice_items.warehouse_id')->update(['invoice_items.warehouse_id' => $warehouseId]);
        }

        Schema::create('warehouse_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 18, 2);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('transferred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
        Schema::dropIfExists('warehouse_product_stocks');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('warehouse_transfers');
    }
};
