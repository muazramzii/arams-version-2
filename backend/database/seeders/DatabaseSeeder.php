<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * ARAMS 2.0 initial data.
 *
 * Reference vocabularies, UTHM organisation structure, the research type
 * registry, the submission state machine, KPI periods and measures, and
 * report definitions. No people and no research records — those arrive
 * through the ARAMS 1.0 migration, which is a separate, reviewed step.
 *
 * Every seeder uses insertOrIgnore, so re-running is safe.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            OrganisationSeeder::class,
            WorkflowSeeder::class,
            KpiSeeder::class,
            ReportDefinitionSeeder::class,
        ]);
    }
}
