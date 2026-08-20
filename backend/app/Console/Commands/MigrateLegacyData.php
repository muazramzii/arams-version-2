<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migrates ARAMS 1.0 into ARAMS 2.0.
 *
 * Defaults to a dry run: every insert and constraint is exercised for real
 * inside a transaction that is then rolled back. That is the only kind of
 * rehearsal worth having — a plan that has only been reasoned about will not
 * tell you that a unique index rejects eleven grant codes.
 *
 *   php artisan arams:migrate-legacy            # rehearse, change nothing
 *   php artisan arams:migrate-legacy --commit   # actually write
 */
class MigrateLegacyData extends Command
{
    protected $signature = 'arams:migrate-legacy
                            {--commit : Write the results instead of rolling back}
                            {--json= : Also write the reconciliation report to this path}';

    protected $description = 'Migrate ARAMS 1.0 data into ARAMS 2.0 (dry run by default)';

    public function handle(LegacyMigrator $migrator): int
    {
        $commit = (bool) $this->option('commit');

        if (! $this->preflight($commit)) {
            return self::FAILURE;
        }

        $runId = DB::table('legacy_migration_runs')->insertGetId([
            'mode'       => $commit ? 'COMMIT' : 'DRY_RUN',
            'status'     => 'RUNNING',
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->newLine();
        $this->line($commit
            ? '<fg=yellow>COMMIT MODE — changes will be written.</>'
            : '<fg=cyan>DRY RUN — everything is rolled back at the end.</>');
        $this->newLine();

        try {
            $report = $migrator->run($commit, fn (string $step) => $this->line("  · {$step}"));
        } catch (\Throwable $e) {
            DB::table('legacy_migration_runs')->where('id', $runId)->update([
                'status'      => 'FAILED',
                'error'       => $e->getMessage(),
                'finished_at' => now(),
                'updated_at'  => now(),
            ]);

            $this->newLine();
            $this->error('Migration failed: ' . $e->getMessage());
            $this->line('<fg=gray>' . $e->getFile() . ':' . $e->getLine() . '</>');

            return self::FAILURE;
        }

        // The run log survives a dry run's rollback because it is written
        // outside the migrator's transaction.
        DB::table('legacy_migration_runs')->where('id', $runId)->update([
            'status'      => 'COMPLETED',
            'report'      => json_encode($report),
            'finished_at' => now(),
            'updated_at'  => now(),
        ]);

        $this->render($report, $commit);

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line("\nFull report written to {$path}");
        }

        return self::SUCCESS;
    }

    private function preflight(bool $commit): bool
    {
        try {
            $sourceUsers = DB::connection('legacy')->table('tbl_user')->count();
        } catch (\Throwable $e) {
            $this->error('Cannot reach the ARAMS 1.0 database. Check LEGACY_DB_* in .env.');

            return false;
        }

        if ($sourceUsers === 0) {
            $this->error('The ARAMS 1.0 database is empty. Nothing to migrate.');

            return false;
        }

        $existing = DB::table('research_records')->count();

        if ($existing > 0) {
            $this->warn("The target already holds {$existing} research record(s).");
            $this->warn('Migrating on top of existing data will duplicate it.');

            if ($commit && ! $this->confirm('Continue anyway?', false)) {
                return false;
            }
        }

        return true;
    }

    private function render(array $report, bool $commit): void
    {
        $this->newLine();
        $this->info('── Reconciliation ──');

        $this->table(['Source (ARAMS 1.0)', 'Rows'], collect($report['source'])
            ->map(fn ($count, $table) => [$table, $count])->values()->all());

        $research = $report['research'];
        $this->table(['Research', 'Value'], [
            ['Legacy parent rows', $research['legacy_parents']],
            ['Records created', $research['records_created']],
            ['<fg=yellow>Bundled submissions split (D3)</>', $research['bundles_split']],
            ['<fg=yellow>Records with no effective date (D4)</>', $research['unknown_effective_dates']],
            ['<fg=red>Approvals with no recorded approver</>', $research['approvals_without_approver']],
        ]);

        $grants = $report['grants'];
        $this->table(['Grants', 'Value'], [
            ['Legacy grant rows', $grants['legacy_rows']],
            ['Projects after deduplication', $grants['projects_created']],
            ['<fg=yellow>Rows sharing a code (merged into one project)</>', $grants['duplicate_claims']],
            // Distinct from the line above: a shared code held by two different
            // lecturers is a legitimate second participant and is kept. Only a
            // repeat claim by the *same* lecturer is dropped.
            ['<fg=yellow>Repeat claims by the same lecturer (dropped)</>',
                $research['grant_claims_collapsed']],
            ['Legacy total value', 'RM ' . number_format($grants['legacy_total_value'], 2)],
            ['Deduplicated total value', 'RM ' . number_format($grants['deduplicated_value'], 2)],
            ['<fg=yellow>Value difference (was triple-counted)</>',
                'RM ' . number_format($grants['value_difference'], 2)],
        ]);

        $this->table(['Other', 'Value'], [
            ['Users migrated', $report['users']['migrated']],
            ['Archived shell accounts', $report['users']['archived_shells']],
            ['Faculty transfers replayed', $report['affiliations']['transfers_replayed']],
            ['TDPP appointments created', $report['appointments']['created']],
            ['H-Index snapshots (out of workflow, D2)', $report['hindex']['migrated']],
            ['Awards entered workflow', $report['awards']['migrated']],
            ['KPI targets', $report['kpi']['targets_created']],
            ['KPI assignments (progress reset)', $report['kpi']['assignments_created']],
            ['Audit events', $report['audit']['migrated']],
        ]);

        $this->newLine();
        $this->info('── Needs a human decision ──');

        $uncovered = $report['appointments']['faculties_with_staff_no_tdpp'];
        if ($uncovered !== []) {
            $this->line('<fg=red>Faculties with staff but no serving TDPP:</> ' . implode(', ', $uncovered));
            $this->line('  <fg=gray>Under D1 nobody can validate there. Appoint one before go-live.</>');
        }

        if ($research['unknown_effective_dates'] > 0) {
            $this->line("<fg=yellow>{$research['unknown_effective_dates']} record(s) have no effective date.</>");
            $this->line('  <fg=gray>Counted in totals, excluded from period KPI. Needs a backfill owner.</>');
        }

        if (! empty($report['duplicate_dois'])) {
            $count = count($report['duplicate_dois']);
            $this->line("<fg=yellow>{$count} duplicate DOI(s) — kept, but the DOI was left blank on the later copy.</>");
        }

        if (! empty($report['hindex']['conflicts_for_review'])) {
            $count = count($report['hindex']['conflicts_for_review']);
            $this->line("<fg=yellow>{$count} conflicting H-Index reading(s) dropped — same staff, source and year.</>");
        }

        $unmapped = $report['reconciliation']['unmapped_vocabulary'];
        if ($unmapped !== []) {
            $this->line('<fg=yellow>Vocabulary values with no mapping rule (stored as NULL):</>');
            foreach ($unmapped as $vocabulary => $values) {
                $sample = collect($values)->map(fn ($n, $v) => "{$v} ×{$n}")->take(4)->implode(', ');
                $this->line("  <fg=gray>{$vocabulary}: {$sample}</>");
            }
        }

        $this->newLine();
        $this->line($commit
            ? '<fg=green>Committed.</>'
            : '<fg=cyan>Rolled back — nothing was written. Re-run with --commit when the items above are settled.</>');
    }
}
