<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The research type registry (D6) and the submission state machine.
 *
 * Both are seeded as data rather than hardcoded, so the legal moves are
 * inspectable in one place and a new research domain can be introduced
 * without touching the workflow.
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * D6 extension point. Adding Research Projects or Postgraduate
         * Supervision later means one row here plus one subtype table —
         * submissions, KPI, audit, notifications and analytics are untouched.
         *
         * effective_date_source names the subtype column that supplies
         * research_records.effective_date, which is what D4 credits against.
         */
        DB::table('research_types')->insertOrIgnore([
            [
                'code' => 'PUBLICATION', 'label' => 'Publication', 'label_ms' => 'Penerbitan',
                'subtype_table' => 'publications', 'model_class' => \App\Models\Publication::class,
                'requires_submission' => true, 'effective_date_source' => 'pub_year',
                'icon' => 'file-text', 'sort_order' => 1, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'code' => 'GRANT', 'label' => 'Research Grant', 'label_ms' => 'Geran Penyelidikan',
                'subtype_table' => 'grants', 'model_class' => \App\Models\Grant::class,
                'requires_submission' => true, 'effective_date_source' => 'grant_project.start_date',
                'icon' => 'award', 'sort_order' => 2, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'code' => 'IP_RECORD', 'label' => 'Intellectual Property', 'label_ms' => 'Harta Intelek',
                'subtype_table' => 'ip_records', 'model_class' => \App\Models\IpRecord::class,
                'requires_submission' => true, 'effective_date_source' => 'filing_date',
                'icon' => 'lightbulb', 'sort_order' => 3, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'code' => 'RESEARCH_INCOME', 'label' => 'Research Income', 'label_ms' => 'Pendapatan Penyelidikan',
                'subtype_table' => 'research_incomes', 'model_class' => \App\Models\ResearchIncome::class,
                'requires_submission' => true, 'effective_date_source' => 'year_received',
                'icon' => 'dollar-sign', 'sort_order' => 4, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'code' => 'AWARD', 'label' => 'Award', 'label_ms' => 'Anugerah',
                'subtype_table' => 'awards', 'model_class' => \App\Models\Award::class,
                'requires_submission' => true, 'effective_date_source' => 'award_year',
                'icon' => 'trophy', 'sort_order' => 5, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        /**
         * D1 is visible in the actor column: every review transition is TDPP.
         * Admin appears only on APPROVED -> SUPERSEDED, which is a data
         * correction, not a validation decision.
         *
         * REJECTED is terminal and REVISION_REQUESTED is not — that distinction
         * is the point. ARAMS 1.0 collapsed both into 'Rejected' and offered no
         * way back, which is the likeliest source of its 16 duplicate publications.
         */
        $transitions = [
            ['DRAFT',              'SUBMITTED',          'OWNER', false, 'Submit for validation'],
            ['DRAFT',              'WITHDRAWN',          'OWNER', false, 'Discard draft'],
            ['SUBMITTED',          'WITHDRAWN',          'OWNER', false, 'Withdraw submission'],
            ['SUBMITTED',          'UNDER_REVIEW',       'TDPP',  false, 'Start review'],
            ['UNDER_REVIEW',       'SUBMITTED',          'TDPP',  false, 'Release claim'],
            ['UNDER_REVIEW',       'APPROVED',           'TDPP',  false, 'Approve'],
            ['UNDER_REVIEW',       'REJECTED',           'TDPP',  true,  'Reject'],
            ['UNDER_REVIEW',       'REVISION_REQUESTED', 'TDPP',  true,  'Request revision'],
            ['REVISION_REQUESTED', 'SUBMITTED',          'OWNER', false, 'Resubmit'],
            ['REVISION_REQUESTED', 'WITHDRAWN',          'OWNER', false, 'Withdraw submission'],
            ['APPROVED',           'SUPERSEDED',         'ADMIN', true,  'Supersede with correction'],
        ];

        $rows = [];
        foreach ($transitions as [$from, $to, $actor, $remarks, $label]) {
            $rows[] = [
                'from_status'      => $from,
                'to_status'        => $to,
                'actor'            => $actor,
                'requires_remarks' => $remarks,
                'label'            => $label,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }
        DB::table('submission_transitions')->insertOrIgnore($rows);
    }
}
