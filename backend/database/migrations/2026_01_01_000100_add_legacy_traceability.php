<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traceability for the ARAMS 1.0 migration.
 *
 * Two things this buys, both of which matter more than the small amount of
 * width they cost:
 *
 *  - The migration becomes re-runnable. Every insert is keyed on its 1.0 id,
 *    so a rehearsal can be repeated without duplicating rows — and the plan
 *    requires the rehearsal to reconcile cleanly twice in a row.
 *
 *  - Any figure in ARAMS 2.0 can be traced back to the row it came from. When
 *    FSKTM asks why its 2020 publication count changed, the answer has to be
 *    demonstrable rather than asserted.
 */
return new class extends Migration
{
    /** table => the 1.0 primary key it descends from */
    private const TARGETS = [
        'users'              => 'tbl_user.user_id',
        'staff_profiles'     => 'tbl_lecturer/tbl_tdpp/tbl_admin id',
        'staff_affiliations' => 'derived',
        'faculty_leaders'    => 'tbl_tdpp.tdpp_id',
        'research_records'   => 'tbl_research_data.data_id',
        'submissions'        => 'tbl_research_data.data_id',
        'grant_projects'     => 'first tbl_grant.grant_id for the code',
        'hindex_snapshots'   => 'tbl_hindex.hindex_id',
        'kpi_targets'        => 'tbl_kpi_target.kpi_id',
        'kpi_assignments'    => 'tbl_kpi_task.task_id',
        'audit_events'       => 'tbl_audit_log.log_id',
    ];

    public function up(): void
    {
        foreach (self::TARGETS as $table => $source) {
            Schema::table($table, function (Blueprint $t) use ($table, $source) {
                $t->unsignedBigInteger('legacy_id')->nullable()->comment("ARAMS 1.0: {$source}");
                $t->string('legacy_source', 40)->nullable();
                $t->index(['legacy_source', 'legacy_id'], "idx_{$table}_legacy");
            });
        }

        /**
         * The audit trail for the data cleaning itself.
         *
         * Every normalisation decision is recorded as a row: 'University' ->
         * UNIVERSITI, 'Focus Group' -> FG, 'Profesor Madya' -> ASSOC_PROF,
         * '' -> UNKNOWN. Without this the migration is a black box, and the
         * 60-odd empty-string values in the 1.0 data would be silently
         * reinterpreted rather than visibly mapped.
         */
        Schema::create('legacy_value_map', function (Blueprint $table) {
            $table->id();
            $table->string('vocabulary', 60);
            $table->string('legacy_value', 191)->nullable();
            $table->string('target_code', 60)->nullable();
            $table->enum('decision', ['MAPPED', 'NORMALISED', 'UNKNOWN', 'DROPPED'])->default('MAPPED');
            $table->unsignedInteger('row_count')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['vocabulary', 'legacy_value'], 'uq_legacy_value');
        });

        /** Per-run reconciliation, so two rehearsals can be compared. */
        Schema::create('legacy_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('mode', ['DRY_RUN', 'COMMIT']);
            $table->enum('status', ['RUNNING', 'COMPLETED', 'FAILED'])->default('RUNNING');
            $table->json('report')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_migration_runs');
        Schema::dropIfExists('legacy_value_map');

        foreach (array_keys(self::TARGETS) as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex("idx_{$table}_legacy");
                $t->dropColumn(['legacy_id', 'legacy_source']);
            });
        }
    }
};
