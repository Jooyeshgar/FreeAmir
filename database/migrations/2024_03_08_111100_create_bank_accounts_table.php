<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBankAccountsTable extends Migration
{
    public function up()
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('number', 40)->unique();
            $table->integer('type');
            $table->string('owner', 50);
            $table->unsignedBigInteger('bank_id');
            $table->string('bank_branch')->nullable();
            $table->string('bank_address')->nullable();
            $table->string('bank_phone')->nullable();
            $table->string('bank_web_page')->nullable();
            $table->text('desc')->nullable();

            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('cascade');

            $table->timestamps(false);
        });
    }
    public function down()
    {
        Schema::dropIfExists('bankAccounts');
    }
}
