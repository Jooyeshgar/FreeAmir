<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $enumValues = [
        'Shipping',
        'Insurance',
        'Customs',
        'Taxes',
        'Loading',
        'Other',
    ];

    public function up(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->dropConstrainedForeignId('product_id');
            $table->decimal('vat', 18, 2)->default(0)->after('amount');
            $table->enum('type', $this->enumValues)->after('amount');
        });

        Schema::create('ancillary_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ancillary_cost_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->enum('type', $this->enumValues);
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('vat', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ancillary_cost_items');

        DB::statement('ALTER TABLE ancillary_costs MODIFY description VARCHAR(200) NOT NULL');

        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->dropColumn('vat');
            $table->foreignId('product_id')->after('invoice_id')->constrained()->onDelete('cascade');
        });
    }
};