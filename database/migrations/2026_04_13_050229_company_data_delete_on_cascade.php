<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });

        Schema::table('salary_decrees', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });

        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('salary_decrees', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
        });
    }
};
