<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_otp')->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_otp_expires_at')->nullable()->after('email_verification_otp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verification_otp', 'email_verification_otp_expires_at']);
        });
    }
};
