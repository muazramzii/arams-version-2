<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — research_records: the domain supertype.
 *
 * This is NOT tbl_research_data reborn. That table was a workflow row
 * pretending to be a parent: it held status, remarks, reviewer and soft-delete
 * flags, nothing queried it for ownership, and it permitted one-to-many
 * children — which is how 67 bundled submissions came to exist.
 *
 * research_records holds only what is true of every research record — owner,
 * type, title, effective date, attributed faculty, soft delete — and carries
 * NO workflow state at all. Workflow lives in `submissions`, which references
 * this table one-to-one via a UNIQUE constraint (that constraint is D3).
 *
 * The payoff: KPI contributions, audit events and notifications reference one
 * stable table instead of five polymorphic targets, "all research owned by
 * this lecturer" is one indexed query rather than a five-way UNION, and the
 * effective-date and attribution rules are defined once rather than five times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_type_id')->constrained('research_types')->restrictOnDelete();
            $table->foreignId('owner_staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();

            // Denormalized from the subtype so queues, lists, notifications and
            // audit lines have a label without a five-way LEFT JOIN.
            // Written in the same transaction as the subtype; checked nightly.
            $table->string('display_title', 500);

            // ── D4: KPI credit follows the record's own effective date ──
            // Nullable only when precision is UNKNOWN. 88 records migrate that
            // way: 70 of 71 grants have no start_date, and all 18 IP records
            // have neither filing_date nor grant_date.
            $table->date('effective_date')->nullable();
            $table->enum('effective_date_precision', ['DAY', 'MONTH', 'YEAR', 'UNKNOWN'])
                  ->default('DAY');

            // ── Historical attribution ──
            // Resolved from staff_affiliations at effective_date when the
            // submission is approved, then frozen. Deliberate denormalization:
            // a published figure must not change because someone later edits
            // an affiliation row. Reconciled nightly, divergence reported.
            $table->foreignId('attributed_faculty_id')->nullable()
                  ->constrained('faculties')->restrictOnDelete();
            $table->dateTime('attributed_at')->nullable();
            $table->enum('attribution_basis', [
                'EFFECTIVE_DATE',
                'SUBMISSION_DATE_FALLBACK',
                'MANUAL',
            ])->nullable();

            // Soft delete is independent of workflow status (locked assumption 6).
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deletion_reason', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();

            // "My research" — the hottest query in the system.
            $table->index(['owner_staff_profile_id', 'research_type_id', 'deleted_at'], 'idx_rr_owner');
            // Faculty analytics and time series.
            $table->index(['attributed_faculty_id', 'effective_date', 'research_type_id'], 'idx_rr_faculty_period');
            // Institution-wide trends.
            $table->index(['effective_date', 'research_type_id'], 'idx_rr_period');
            // The Admin "records needing a date" worklist.
            $table->index('effective_date_precision', 'idx_rr_precision');
        });

        // A missing date must always be declared, never implied. This is the
        // guarantee that lets UNKNOWN records be excluded from period-scoped
        // KPI rather than silently defaulting into the wrong period.
        DB::statement("ALTER TABLE `research_records` ADD CONSTRAINT `chk_rr_effective_date`
                       CHECK (effective_date IS NOT NULL OR effective_date_precision = 'UNKNOWN')");
    }

    public function down(): void
    {
        Schema::dropIfExists('research_records');
    }
};
