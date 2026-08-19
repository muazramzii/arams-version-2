<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Report definitions.
 *
 * In ARAMS 1.0 report_type was an ENUM of seven names, while
 * api/generate_report.php wrote 'publications', 'grants' and 'comprehensive' —
 * none of which were members. With MariaDB in non-strict mode those were
 * silently coerced to '', so 52 of 57 rows record nothing about what was
 * reported. Here the type is a foreign key, so the mismatch cannot occur.
 */
class ReportDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['PUBLICATIONS', 'Publications Report',
             'Validated publications with indexing, quartile and collaboration detail.', 'TDPP'],
            ['GRANTS', 'Grants and Funding Report',
             'Grant projects and participation, by level, category and funder.', 'TDPP'],
            ['RESEARCH_INCOME', 'Research Income Report',
             'Income received, by category and source, reconciled against grants.', 'TDPP'],
            ['IP', 'Intellectual Property Report',
             'Filed and awarded IP by type and registration status.', 'TDPP'],
            ['AWARDS', 'Awards Report', 'Awards received, by level and organiser.', 'TDPP'],
            ['HINDEX', 'H-Index Report',
             'Latest H-Index and citation snapshots per researcher, by source.', 'TDPP'],
            ['FACULTY_PERFORMANCE', 'Faculty Performance Report',
             'Output, funding and KPI achievement for a faculty over a period.', 'TDPP'],
            ['INSTITUTIONAL_SUMMARY', 'Institutional Research Summary',
             'University-wide research performance across all faculties.', 'Admin'],
            ['LECTURER_PROFILE', 'Individual Lecturer Report',
             'Complete validated research record for one researcher.', 'Lecturer'],
            ['KPI_ACHIEVEMENT', 'KPI Achievement Report',
             'Targets against achieved values for a period, with contributing records.', 'TDPP'],
            ['VALIDATION_BACKLOG', 'Validation Backlog Report',
             'Pending submissions by age and faculty, including faculties with no active TDPP.', 'Admin'],
            ['DATA_QUALITY', 'Data Quality Report',
             'Records needing attention: unknown effective dates, missing identifiers, unresolved duplicates.', 'Admin'],
        ];

        $rows = [];
        foreach ($definitions as [$code, $title, $description, $minRole]) {
            $rows[] = [
                'code'             => $code,
                'title'            => $title,
                'description'      => $description,
                'parameter_schema' => json_encode([
                    'period_id'   => ['type' => 'integer', 'required' => false],
                    'faculty_id'  => ['type' => 'integer', 'required' => false],
                    'staff_id'    => ['type' => 'integer', 'required' => false],
                    'format'      => ['type' => 'string',  'enum' => ['PDF', 'XLSX', 'CSV']],
                    // Default is approved-only. Anything else must be asked for
                    // explicitly and stamps a banner on the output.
                    'include_unvalidated' => ['type' => 'boolean', 'default' => false],
                ]),
                'minimum_role'     => $minRole,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }
        DB::table('report_definitions')->insertOrIgnore($rows);
    }
}
