<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets one measure carry several targets in a period, distinguished by criteria.
 *
 * Found by rehearsing the ARAMS 1.0 migration, not by reasoning about it. UTHM's
 * real 2025 targets include "Publications 600", "Q1 Publications 80" and "Q2
 * Publications 150" — same measure, same scope, differing only by a quartile
 * criterion. The original unique key (period, measure, scope_type, scope_id)
 * could not tell them apart and rejected the second and third.
 *
 * Widening the key keeps what the constraint was for — no two contradictory
 * targets for the same thing — while admitting the distinction the institution
 * actually makes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded because an earlier attempt added the column before failing on
        // the index rebuild below.
        if (! Schema::hasColumn('kpi_targets', 'variant_code')) {
            Schema::table('kpi_targets', function (Blueprint $table) {
                $table->string('variant_code', 40)->nullable()
                    ->after('scope_id')
                    ->comment('Distinguishes criteria-bearing targets on one measure, e.g. Q1');
            });
        }

        /**
         * Order matters twice over:
         *
         *  - MySQL will not drop and re-add one index name inside a single
         *    ALTER TABLE, so these are separate statements.
         *  - The old unique is the index the kpi_period_id foreign key relies
         *    on, and MySQL refuses to drop it while it is the only candidate.
         *    Adding the replacement first gives the FK somewhere else to point.
         *
         * The new index therefore keeps its own name; MariaDB 10.4 has no
         * RENAME INDEX, and a rename would buy nothing but tidiness.
         */
        if (! $this->hasIndex('uq_kpi_target_scope_variant')) {
            DB::statement(
                'ALTER TABLE `kpi_targets` ADD UNIQUE `uq_kpi_target_scope_variant`
                 (`kpi_period_id`, `kpi_measure_id`, `scope_type`, `scope_id`, `variant_code`)'
            );
        }

        if ($this->hasIndex('uq_kpi_target_scope')) {
            DB::statement('ALTER TABLE `kpi_targets` DROP INDEX `uq_kpi_target_scope`');
        }
    }

    private function hasIndex(string $name): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'kpi_targets')
            ->where('INDEX_NAME', $name)
            ->exists();
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE `kpi_targets` ADD UNIQUE `uq_kpi_target_scope`
             (`kpi_period_id`, `kpi_measure_id`, `scope_type`, `scope_id`)'
        );
        DB::statement('ALTER TABLE `kpi_targets` DROP INDEX `uq_kpi_target_scope_variant`');

        Schema::table('kpi_targets', function (Blueprint $table) {
            $table->dropColumn('variant_code');
        });
    }
};
