<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — Audit, notifications, reporting, and analytics read models.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Append-only. Three changes from tbl_audit_log:
         *  - `action` is a typed enum, not free text (1.0 contains the typo
         *    'Rejectd Submission')
         *  - emitted from the service layer, so coverage follows significance
         *    (1.0 has 7 Research_Data audit rows against 272 approvals)
         *  - `changes` records what actually changed, which 1.0 never captured
         */
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20)->nullable();
            $table->string('action', 60);

            // Intentionally polymorphic with NO foreign key: audit rows must
            // survive the deletion of what they describe. This is the one place
            // where referential integrity would be actively wrong.
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->json('changes')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'idx_audit_subject');
            $table->index(['actor_user_id', 'created_at'], 'idx_audit_actor');
            $table->index(['action', 'created_at'], 'idx_audit_action');
        });

        /**
         * The message is rendered at display time from type + data, not stored
         * as a pre-built sentence. That is what makes notifications
         * translatable into Malay, filterable, groupable, and linkable —
         * none of which 1.0's CONCAT-built strings support.
         */
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 150);
            $table->foreignId('notifiable_user_id')->constrained('users')->cascadeOnDelete();
            $table->json('data');
            $table->string('action_url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_user_id', 'read_at', 'created_at'], 'idx_notif_inbox');
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 150);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(true);
            $table->boolean('digest')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'type'], 'uq_notif_pref');
        });

        // Report type becomes a foreign key rather than an ENUM the code never
        // writes valid values for — 52 of 57 rows in tbl_report hold ''.
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('title', 150);
            $table->string('description', 500)->nullable();
            $table->json('parameter_schema')->nullable();
            $table->enum('minimum_role', ['Lecturer', 'TDPP', 'Admin'])->default('Admin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_definition_id')->constrained('report_definitions')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->json('parameters')->nullable();

            // Scope applied at generation, so the artifact is permanently bound
            // to it — a TDPP's faculty report cannot later widen.
            $table->enum('scope_type', ['INSTITUTION', 'FACULTY', 'STAFF']);
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->enum('format', ['PDF', 'XLSX', 'CSV']);
            $table->enum('status', ['QUEUED', 'RUNNING', 'READY', 'FAILED'])->default('QUEUED');
            $table->unsignedInteger('row_count')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('file_hash', 64)->nullable()
                  ->comment('Traces a printed report back to the exact artifact');
            $table->dateTime('generated_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'created_at'], 'idx_run_requester');
            $table->index(['status', 'expires_at'], 'idx_run_status');
        });

        // Materialised read models, refreshed nightly and on approval.
        // Replace vw_lecturer_kpi, which computed "current H-Index" as MAX()
        // across all years rather than the latest year's value.
        Schema::create('analytics_faculty_period', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('research_type_id')->nullable()
                  ->constrained('research_types')->cascadeOnDelete();
            $table->unsignedInteger('record_count')->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->unsignedInteger('active_staff_count')->default(0);
            $table->json('breakdown')->nullable();
            $table->dateTime('computed_at');
            $table->timestamps();

            $table->unique(['faculty_id', 'kpi_period_id', 'research_type_id'], 'uq_afp');
        });

        Schema::create('analytics_staff_period', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('research_type_id')->nullable()
                  ->constrained('research_types')->cascadeOnDelete();
            $table->unsignedInteger('record_count')->default(0);
            $table->decimal('total_value', 18, 2)->default(0);
            $table->unsignedSmallInteger('latest_hindex')->nullable()
                  ->comment('Value at the most recent record_year, not MAX()');
            $table->json('breakdown')->nullable();
            $table->dateTime('computed_at');
            $table->timestamps();

            $table->unique(['staff_profile_id', 'kpi_period_id', 'research_type_id'], 'uq_asp');
        });

        // D5: a benchmark is released only when enough faculties report, so a
        // TDPP cannot subtract their own figure and read another faculty's.
        Schema::create('benchmark_cohorts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('label', 150);
            $table->foreignId('kpi_measure_id')->constrained('kpi_measures')->cascadeOnDelete();
            $table->unsignedSmallInteger('min_cohort_size')->default(3)
                  ->comment('Faculties excluding the requester needed to release a value');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_cohorts');
        Schema::dropIfExists('analytics_staff_period');
        Schema::dropIfExists('analytics_faculty_period');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_definitions');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_events');
    }
};
