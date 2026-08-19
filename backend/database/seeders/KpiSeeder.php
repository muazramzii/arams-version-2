<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * KPI periods and measures.
 *
 * Periods are the concept ARAMS 1.0 had no column for at all: tbl_kpi_task
 * carried only a deadline, so the matcher counted a lecturer's entire career.
 * One live task reads target_count 1 against progress_count 19.
 *
 * source_kind separates the two things KPI can measure. RESEARCH_RECORD
 * measures count validated submissions; METRIC_SNAPSHOT measures read
 * hindex_snapshots, which under D2 sit outside the workflow entirely. Without
 * that split, D2 would have silently killed the institutional H-Index targets
 * that already exist in tbl_kpi_target.
 */
class KpiSeeder extends Seeder
{
    public function run(): void
    {
        $periods = [];
        foreach (range(2024, 2028) as $year) {
            $periods[] = [
                'code'       => (string) $year,
                'label'      => "Calendar Year {$year}",
                'start_date' => "{$year}-01-01",
                'end_date'   => "{$year}-12-31",
                'is_locked'  => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('kpi_periods')->insertOrIgnore($periods);

        $types = DB::table('research_types')->pluck('id', 'code');

        $measures = [
            ['PUBLICATION_COUNT', 'Publications',        'RESEARCH_RECORD', $types['PUBLICATION'],     'COUNT', null,     'papers'],
            ['GRANT_COUNT',       'Research Grants',     'RESEARCH_RECORD', $types['GRANT'],           'COUNT', null,     'grants'],
            ['GRANT_VALUE',       'Grant Value',         'RESEARCH_RECORD', $types['GRANT'],           'SUM',   'allocated_amount', 'MYR'],
            ['IP_COUNT',          'Intellectual Property', 'RESEARCH_RECORD', $types['IP_RECORD'],     'COUNT', null,     'records'],
            ['INCOME_TOTAL',      'Research Income',     'RESEARCH_RECORD', $types['RESEARCH_INCOME'], 'SUM',   'amount', 'MYR'],
            ['AWARD_COUNT',       'Awards',              'RESEARCH_RECORD', $types['AWARD'],           'COUNT', null,     'awards'],
            // D2: reads hindex_snapshots directly, not submissions.
            ['HINDEX_AVERAGE',    'Average H-Index',     'METRIC_SNAPSHOT', null,                      'AVG',   'hindex_value', 'index'],
            ['HINDEX_MAX',        'Highest H-Index',     'METRIC_SNAPSHOT', null,                      'MAX',   'hindex_value', 'index'],
        ];

        $rows = [];
        foreach ($measures as [$code, $label, $sourceKind, $typeId, $agg, $column, $unit]) {
            $rows[] = [
                'code'             => $code,
                'label'            => $label,
                'source_kind'      => $sourceKind,
                'research_type_id' => $typeId,
                'aggregation'      => $agg,
                'value_column'     => $column,
                'unit'             => $unit,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }
        DB::table('kpi_measures')->insertOrIgnore($rows);

        // D5: a benchmark is released only when enough faculties report, so a
        // TDPP cannot subtract their own figure and read another faculty's.
        // Three is close to the limit with only six faculties currently
        // holding data — Q5 in the Phase 2 document asks you to confirm it.
        $measureIds = DB::table('kpi_measures')->pluck('id', 'code');
        $cohorts = [];
        foreach (['PUBLICATION_COUNT', 'GRANT_COUNT', 'INCOME_TOTAL', 'HINDEX_AVERAGE'] as $code) {
            $cohorts[] = [
                'code'            => 'FACULTY_' . $code,
                'label'           => 'Faculty benchmark — ' . $code,
                'kpi_measure_id'  => $measureIds[$code],
                'min_cohort_size' => 3,
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }
        DB::table('benchmark_cohorts')->insertOrIgnore($cohorts);
    }
}
