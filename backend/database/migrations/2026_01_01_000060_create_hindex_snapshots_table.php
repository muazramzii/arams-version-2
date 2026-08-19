<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARAMS 2.0 — H-Index snapshots (D2).
 *
 * No submission FK. No status. No reviewer. H-Index is a metric published by
 * Scopus or Web of Science, not an achievement a lecturer reports — asking a
 * TDPP to "approve" it is a review step with no decision in it. Trust comes
 * from provenance (who recorded it, when, from which source) rather than from
 * an approval that could only confirm the value was copied correctly.
 *
 * This removes 87 records from the validation workflow, including the 77-row
 * bulk import that generated 77 no-op approvals in a single batch.
 *
 * KPI still scores H-Index via kpi_measures.source_kind = 'METRIC_SNAPSHOT',
 * so the institutional "average H-Index" targets in tbl_kpi_target survive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hindex_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->restrictOnDelete();
            $table->foreignId('metric_source_id')->constrained('metric_sources')->restrictOnDelete();

            $table->unsignedSmallInteger('record_year');
            $table->date('effective_date')->nullable()->comment('Date the value was observed');

            $table->unsignedSmallInteger('hindex_value');
            $table->unsignedInteger('citation_count')->nullable();
            $table->unsignedInteger('document_count')->nullable();

            // Provenance replaces approval.
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');
            $table->string('source_note', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();

            // A real constraint, unlike the 1.0 uq_hindex_year_lecturer, which
            // was unique on (record_year, data_id) — already unique by
            // construction, so it enforced nothing. One existing row violates
            // this: lecturer 2 has two 2025 readings, to be resolved manually.
            $table->unique(['staff_profile_id', 'metric_source_id', 'record_year'], 'uq_hindex_snapshot');
            $table->index(['record_year', 'metric_source_id'], 'idx_hindex_year');
        });

        DB::statement('ALTER TABLE `hindex_snapshots` ADD CONSTRAINT `chk_hindex_year`
                       CHECK (record_year BETWEEN 1950 AND 2100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hindex_snapshots');
    }
};
