<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — Staff, affiliation history, and faculty appointments.
 *
 * Three changes from 1.0, each fixing a measured defect:
 *
 *  1. staff_profiles merges tbl_lecturer + tbl_tdpp + tbl_admin. The same
 *     attribute was stored three times; api/update_user.php wrote to two of
 *     the three and silently missed TDPP names entirely.
 *
 *  2. staff_affiliations makes faculty membership effective-dated. In 1.0,
 *     analytics attributed research through the lecturer's CURRENT faculty,
 *     so when lecturer 1 transferred FSKTM -> FKAAB, 37 records of output
 *     dating back to 2016 moved with her and both faculties' history changed.
 *
 *  3. faculty_leaders makes TDPP a dated appointment rather than a profile
 *     row. Under D1 the TDPP is the only validator, so "which faculties have
 *     no validator?" must be answerable — FKAAS currently has 77 lecturers
 *     and no appointment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('staff_no', 30)->unique();
            $table->string('full_name', 191);
            $table->string('title', 50)->nullable()->comment('Dr., Prof., Ts.');

            $table->foreignId('position_id')->nullable()
                  ->constrained('positions')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()
                  ->constrained('grades')->nullOnDelete();
            $table->foreignId('researcher_status_id')->nullable()
                  ->constrained('researcher_statuses')->nullOnDelete();

            $table->string('phone', 30)->nullable();
            $table->string('specialisation', 255)->nullable();
            $table->boolean('managerial_position')->default(false);
            $table->string('profile_photo_path', 255)->nullable()
                  ->comment('Stored outside the web root, served by controller');
            $table->string('cv_url', 255)->nullable();

            // Migrated shell accounts: present for attribution, excluded from
            // per-capita metrics until a real person activates them.
            $table->boolean('is_archived')->default(false);

            $table->softDeletes();
            $table->timestamps();

            $table->index('full_name', 'idx_staff_name');
            $table->index('is_archived', 'idx_staff_archived');
        });

        Schema::create('staff_external_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('external_id_provider_id')->constrained('external_id_providers')->restrictOnDelete();
            $table->string('value', 191);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['staff_profile_id', 'external_id_provider_id'], 'uq_staff_provider');
            // Catches two people claiming the same ORCID.
            $table->unique(['external_id_provider_id', 'value'], 'uq_provider_value');
        });

        Schema::create('staff_affiliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('faculty_id')->constrained('faculties')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()
                  ->constrained('departments')->nullOnDelete();
            $table->foreignId('research_group_id')->nullable()
                  ->constrained('research_groups')->nullOnDelete();

            $table->date('valid_from');
            $table->date('valid_to')->nullable()->comment('NULL = current');
            $table->boolean('is_primary')->default(true);
            $table->string('transfer_reason', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Point-in-time attribution resolution — the hot path for analytics.
            $table->index(['staff_profile_id', 'valid_from', 'valid_to'], 'idx_affil_point_in_time');
            $table->index(['faculty_id', 'valid_to'], 'idx_affil_current_roster');
        });

        Schema::create('faculty_leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->restrictOnDelete();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->enum('appointment', ['TDPP'])->default('TDPP');
            $table->date('valid_from');
            $table->date('valid_to')->nullable()->comment('NULL = currently serving');
            $table->foreignId('appointed_by')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            // "Who validates here?" and "which faculties have no validator?"
            $table->index(['faculty_id', 'valid_to', 'appointment'], 'idx_leader_active');
            $table->index('staff_profile_id', 'idx_leader_staff');
        });

        // Date sanity is enforced by CHECK; overlap between two open ranges
        // needs cross-row logic, which MySQL/MariaDB cannot express — that
        // rule lives in the service layer and is covered by tests.
        DB::statement('ALTER TABLE `staff_affiliations` ADD CONSTRAINT `chk_affil_dates`
                       CHECK (valid_to IS NULL OR valid_to >= valid_from)');
        DB::statement('ALTER TABLE `faculty_leaders` ADD CONSTRAINT `chk_leader_dates`
                       CHECK (valid_to IS NULL OR valid_to >= valid_from)');
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_leaders');
        Schema::dropIfExists('staff_affiliations');
        Schema::dropIfExists('staff_external_ids');
        Schema::dropIfExists('staff_profiles');
    }
};

