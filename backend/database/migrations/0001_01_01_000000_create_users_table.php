<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — Identity & Access
 *
 * Replaces tbl_user. Role stays here; the person's profile moves to
 * staff_profiles, so a TDPP and an Admin can both have a real profile
 * (ARAMS 1.0 gave them separate, half-populated tables).
 *
 * The OTP-based password reset from ARAMS 1.0 is preserved rather than
 * swapped for Laravel's token/link flow — it is a working feature staff
 * already know, and the 1.0 implementation was sound.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->enum('role', ['Lecturer', 'TDPP', 'Admin'])->default('Lecturer');
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['role', 'is_active'], 'idx_users_role_active');
        });

        // OTP codes for the Forgot Password flow (carried over from 1.0).
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150);
            $table->string('code_hash');            // never store the code itself
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('used')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'used', 'expires_at'], 'idx_reset_lookup');
        });

        // Brute-force throttling (carried over from 1.0).
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('email', 150)->nullable();
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['ip_address', 'attempted_at'], 'idx_attempt_ip_time');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('password_reset_codes');
        Schema::dropIfExists('users');
    }
};
