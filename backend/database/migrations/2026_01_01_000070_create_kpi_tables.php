<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — KPI (D4).
 *
 * ARAMS 1.0 had two disconnected halves: institutional targets nobody read
 * (tbl_kpi_target, 12 rows, zero code references) and individual tasks that
 * counted the wrong thing (tbl_kpi_task — no period column at all, so the
 * matcher counted a lecturer's entire career; task 1 reads target 1,
 * progress 19).
 *
 * Here both are kpi_targets differing only in scope_type. Progress computes
 * identically for institution, faculty, and staff scope.
 *
 * D4: credit follows the research record's own effective_date, so a
 * publication dated December 2026 and approved in January 2027 credits
 * period 2026. Validation delay can no longer distort performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The concept 1.0 had no column for.
        Schema::create('kpi_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('e.g. 2026, 2026-H1');
            $table->string('label', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(false)
                  ->comment('Locked periods are closed to recomputation');
            $table->timestamps();

            $table->index(['start_date', 'end_date'], 'idx_period_range');
        });

        Schema::create('kpi_measures', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('label', 150);

            // Lets an institutional "average H-Index" target survive D2, since
            // snapshots live outside the research/submission workflow.
            $table->enum('source_kind', ['RESEARCH_RECORD', 'METRIC_SNAPSHOT']);
            $table->foreignId('research_type_id')->nullable()
                  ->constrained('research_types')->restrictOnDelete();

            $table->enum('aggregation', ['COUNT', 'SUM', 'AVG', 'MAX']);
            $table->string('value_column', 64)->nullable()
                  ->comment('Column aggregated when aggregation is not COUNT');
            $table->string('unit', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('kpi_measure_id')->constrained('kpi_measures')->restrictOnDelete();

            $table->enum('scope_type', ['INSTITUTION', 'FACULTY', 'STAFF']);
            $table->unsignedBigInteger('scope_id')->nullable()
                  ->comment('faculty_id or staff_profile_id; NULL for INSTITUTION');

            $table->decimal('target_value', 15, 2);
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['kpi_period_id', 'kpi_measure_id', 'scope_type', 'scope_id'],
                'uq_kpi_target_scope'
            );
            $table->index(['scope_type', 'scope_id'], 'idx_target_scope');
        });

        // Criteria as ROWS, not columns. In 1.0 they were four fixed columns on
        // tbl_kpi_task, so adding a criterion meant a schema change — and the
        // matcher compared a SET column with `=`, silently missing every
        // publication indexed 'Scopus,WoS'. The `contains` operator fixes that
        // class of bug structurally.
        Schema::create('kpi_target_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_target_id')->constrained('kpi_targets')->cascadeOnDelete();
            $table->string('field_path', 100)->comment('e.g. publication.quartile');
            $table->enum('operator', ['=', '!=', 'in', '>=', '<=', '>', '<', 'contains']);
            $table->string('value', 255);
            $table->timestamps();

            $table->index('kpi_target_id', 'idx_criteria_target');
        });

        Schema::create('kpi_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_target_id')->constrained('kpi_targets')->cascadeOnDelete();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->cascadeOnDelete();
            $table->foreignId('assigned_by_staff_profile_id')->nullable()
                  ->constrained('staff_profiles')->nullOnDelete();

            $table->dateTime('assigned_at');
            $table->date('deadline')->nullable();
            $table->enum('status', ['OPEN', 'MET', 'MET_LATE', 'MISSED', 'CANCELLED'])->default('OPEN');
            $table->dateTime('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['kpi_target_id', 'staff_profile_id'], 'uq_assignment');
            $table->index(['staff_profile_id', 'status'], 'idx_assignment_staff');
            $table->index('deadline', 'idx_assignment_deadline');
        });

        // Materialised because dashboards read it constantly. Derived from
        // kpi_contributions and recomputed idempotently — it cannot drift the
        // way tbl_kpi_task.progress_count did.
        Schema::create('kpi_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_target_id')->constrained('kpi_targets')->cascadeOnDelete();
            $table->foreignId('kpi_assignment_id')->nullable()
                  ->constrained('kpi_assignments')->cascadeOnDelete();

            $table->decimal('achieved_value', 15, 2)->default(0);
            $table->decimal('target_value', 15, 2);
            $table->decimal('percentage', 6, 2)->default(0);
            $table->dateTime('computed_at');
            $table->timestamps();

            $table->unique(['kpi_target_id', 'kpi_assignment_id'], 'uq_progress');
        });

        // The difference between a progress number and an auditable one: a
        // lecturer can see exactly which records counted, and progress falls
        // when a record is withdrawn because its contribution is removed.
        Schema::create('kpi_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_target_id')->constrained('kpi_targets')->cascadeOnDelete();
            $table->foreignId('kpi_assignment_id')->nullable()
                  ->constrained('kpi_assignments')->cascadeOnDelete();

            // Exactly one of these is set, per source_kind.
            $table->foreignId('research_record_id')->nullable()
                  ->constrained('research_records')->cascadeOnDelete();
            $table->foreignId('hindex_snapshot_id')->nullable()
                  ->constrained('hindex_snapshots')->cascadeOnDelete();

            $table->decimal('contributed_value', 15, 2)->default(1);
            $table->date('counted_on')->comment("The record's effective date — D4");
            $table->timestamps();

            $table->unique(['kpi_target_id', 'kpi_assignment_id', 'research_record_id'], 'uq_contrib_record');
            $table->index('research_record_id', 'idx_contrib_record');
            $table->index('hindex_snapshot_id', 'idx_contrib_snapshot');
        });

        DB::statement('ALTER TABLE `kpi_periods` ADD CONSTRAINT `chk_period_range`
                       CHECK (end_date >= start_date)');
        DB::statement('ALTER TABLE `kpi_targets` ADD CONSTRAINT `chk_target_value`
                       CHECK (target_value > 0)');
        // Institution scope has no scope_id; faculty and staff scope must have one.
        DB::statement("ALTER TABLE `kpi_targets` ADD CONSTRAINT `chk_target_scope`
                       CHECK ((scope_type = 'INSTITUTION' AND scope_id IS NULL)
                           OR (scope_type <> 'INSTITUTION' AND scope_id IS NOT NULL))");
        // A contribution comes from exactly one source.
        DB::statement('ALTER TABLE `kpi_contributions` ADD CONSTRAINT `chk_contrib_source`
                       CHECK ((research_record_id IS NOT NULL AND hindex_snapshot_id IS NULL)
                           OR (research_record_id IS NULL AND hindex_snapshot_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_contributions');
        Schema::dropIfExists('kpi_progress');
        Schema::dropIfExists('kpi_assignments');
        Schema::dropIfExists('kpi_target_criteria');
        Schema::dropIfExists('kpi_targets');
        Schema::dropIfExists('kpi_measures');
        Schema::dropIfExists('kpi_periods');
    }
};
