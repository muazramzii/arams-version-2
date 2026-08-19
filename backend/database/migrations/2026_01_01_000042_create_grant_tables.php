<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — Grants, split into project and participation.
 *
 * The real 1.0 data forces this. Grant code Q940 appears three times, once
 * per participating lecturer; ten other codes appear twice. Under 1.0 those
 * are three unrelated grants worth three times the money, and institutional
 * funding totals triple-count them.
 *
 *   grant_projects  the institutional grant — one per award, shared
 *   grants          one lecturer's participation — the research record that
 *                   is submitted and validated
 *
 * D3 still holds: each lecturer's participation is one research record with
 * one submission, reviewed by their own faculty's TDPP — which is correct,
 * since two participants may sit in different faculties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grant_projects', function (Blueprint $table) {
            $table->id();
            // Unique — this is what stops the triple-counting.
            $table->string('grant_code', 100)->unique();
            $table->string('title', 500);

            $table->foreignId('funder_id')->nullable()
                  ->constrained('funders')->restrictOnDelete();
            $table->foreignId('grant_category_id')->nullable()
                  ->constrained('grant_categories')->restrictOnDelete();
            $table->foreignId('grant_level_id')->nullable()
                  ->constrained('grant_levels')->restrictOnDelete();
            $table->foreignId('grant_status_id')->nullable()
                  ->constrained('grant_statuses')->restrictOnDelete();

            $table->decimal('total_amount', 15, 2)->nullable();
            $table->char('currency', 3)->default('MYR');
            $table->date('start_date')->nullable()
                  ->comment('70 of 71 rows in the 1.0 data are NULL — needs backfill');
            $table->date('end_date')->nullable();
            $table->string('mygrants_id', 50)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['grant_level_id', 'start_date'], 'idx_gp_level_date');
            $table->index('grant_status_id', 'idx_gp_status');
        });

        Schema::create('grants', function (Blueprint $table) {
            $table->foreignId('research_record_id')->primary()
                  ->constrained('research_records')->cascadeOnDelete();
            $table->foreignId('grant_project_id')->constrained('grant_projects')->restrictOnDelete();
            $table->foreignId('grant_role_id')->constrained('grant_roles')->restrictOnDelete();
            $table->decimal('allocated_amount', 15, 2)->nullable()
                  ->comment("This member's share, where UTHM tracks it");

            // Denormalized from research_records.owner_staff_profile_id purely so
            // the duplicate-claim rule can be a real UNIQUE constraint rather than
            // a service-layer convention. Safe to copy: a record's owner is
            // immutable. This is the exact defect it prevents — all 11 duplicate
            // grant codes in the 1.0 data are one lecturer claiming one grant twice.
            $table->foreignId('owner_staff_profile_id')
                  ->constrained('staff_profiles')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['grant_project_id', 'owner_staff_profile_id'], 'uq_grant_project_owner');
            $table->index('grant_project_id', 'idx_grant_project');
            $table->index('grant_role_id', 'idx_grant_role');
        });

        DB::statement('ALTER TABLE `grant_projects` ADD CONSTRAINT `chk_gp_dates`
                       CHECK (end_date IS NULL OR start_date IS NULL OR end_date >= start_date)');
        DB::statement('ALTER TABLE `grant_projects` ADD CONSTRAINT `chk_gp_amount`
                       CHECK (total_amount IS NULL OR total_amount >= 0)');
        DB::statement('ALTER TABLE `grants` ADD CONSTRAINT `chk_grant_alloc`
                       CHECK (allocated_amount IS NULL OR allocated_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('grants');
        Schema::dropIfExists('grant_projects');
    }
};
