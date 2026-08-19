<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\ResearchRecord;
use App\Models\ResearchType;
use App\Models\StaffProfile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves the locked decisions are enforced by the database, not by convention.
 *
 * Each test reproduces a defect measured in the real ARAMS 1.0 data and
 * asserts that ARAMS 2.0 makes it impossible to store.
 */
class SchemaConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function staff(string $email = 'lecturer@uthm.edu.my'): StaffProfile
    {
        $user = User::create([
            'email'     => $email,
            'password'  => 'secret-password',
            'role'      => 'Lecturer',
            'is_active' => true,
        ]);

        return StaffProfile::create([
            'user_id'   => $user->id,
            'staff_no'  => 'UTH' . random_int(10000, 99999),
            'full_name' => 'Test Lecturer',
        ]);
    }

    private function record(StaffProfile $staff, array $overrides = []): ResearchRecord
    {
        return ResearchRecord::create(array_merge([
            'research_type_id'         => ResearchType::where('code', 'PUBLICATION')->value('id'),
            'owner_staff_profile_id'   => $staff->id,
            'display_title'            => 'A Test Publication',
            'effective_date'           => '2026-03-01',
            'effective_date_precision' => 'DAY',
        ], $overrides));
    }

    #[Test]
    public function seed_data_is_present(): void
    {
        $this->assertSame(12, Faculty::count(), 'all 12 UTHM faculties');
        $this->assertSame(5, ResearchType::count(), 'five research types (D6 registry)');
        $this->assertSame(196, DB::table('countries')->count());
        $this->assertSame(21, DB::table('grant_categories')->count(), 'FRT cascade');
        $this->assertSame(11, DB::table('submission_transitions')->count());
        $this->assertSame(8, DB::table('kpi_measures')->count());
    }

    /**
     * D3 — one submission = exactly one research record.
     * 67 of 278 submissions in the 1.0 data held more than one record.
     */
    #[Test]
    public function d3_a_research_record_cannot_have_two_submissions(): void
    {
        $staff  = $this->staff();
        $record = $this->record($staff);

        Submission::create([
            'research_record_id' => $record->id,
            'status'             => 'SUBMITTED',
            'submitted_by'       => $staff->user_id,
            'submitted_at'       => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Submission::create([
            'research_record_id' => $record->id,
            'status'             => 'SUBMITTED',
            'submitted_by'       => $staff->user_id,
            'submitted_at'       => now(),
        ]);
    }

    /**
     * D4 — a missing effective date must be declared, never implied, so that
     * UNKNOWN records can be excluded from period-scoped KPI rather than
     * silently defaulting into the wrong period.
     */
    #[Test]
    public function d4_a_null_effective_date_requires_unknown_precision(): void
    {
        $staff = $this->staff();

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->record($staff, [
            'effective_date'           => null,
            'effective_date_precision' => 'DAY',
        ]);
    }

    #[Test]
    public function d4_unknown_precision_permits_a_null_date(): void
    {
        $staff = $this->staff();

        $record = $this->record($staff, [
            'effective_date'           => null,
            'effective_date_precision' => 'UNKNOWN',
        ]);

        $this->assertTrue($record->needsDateBackfill());
        // Excluded from period scoping, but still a real, countable record —
        // this is how the 88 dateless 1.0 records migrate.
        $this->assertSame(0, ResearchRecord::datePlaceable()->count());
        $this->assertSame(1, ResearchRecord::count());
    }

    /**
     * The 1.0 data holds 11 grant codes claimed more than once by the same
     * lecturer, which triple-counted institutional funding.
     */
    #[Test]
    public function a_lecturer_cannot_claim_the_same_grant_twice(): void
    {
        $staff = $this->staff();

        $projectId = DB::table('grant_projects')->insertGetId([
            'grant_code' => 'Q940', 'title' => 'Shared Grant',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $roleId = DB::table('grant_roles')->where('code', 'MEMBER')->value('id');
        $grantType = ResearchType::where('code', 'GRANT')->value('id');

        foreach ([1, 2] as $attempt) {
            $record = $this->record($staff, [
                'research_type_id' => $grantType,
                'display_title'    => "Claim {$attempt}",
            ]);

            if ($attempt === 2) {
                $this->expectException(\Illuminate\Database\QueryException::class);
            }

            DB::table('grants')->insert([
                'research_record_id'     => $record->id,
                'grant_project_id'       => $projectId,
                'grant_role_id'          => $roleId,
                'owner_staff_profile_id' => $staff->id,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }
    }

    /**
     * The 1.0 constraint uq_hindex_year_lecturer(record_year, data_id) enforced
     * nothing, because data_id was already unique per submission. Lecturer 2
     * consequently has two conflicting 2025 readings.
     */
    #[Test]
    public function hindex_is_unique_per_staff_source_and_year(): void
    {
        $staff    = $this->staff();
        $sourceId = DB::table('metric_sources')->where('code', 'SCOPUS')->value('id');

        $row = [
            'staff_profile_id' => $staff->id,
            'metric_source_id' => $sourceId,
            'record_year'      => 2025,
            'hindex_value'     => 12,
            'recorded_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        DB::table('hindex_snapshots')->insert($row);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('hindex_snapshots')->insert(array_merge($row, ['hindex_value' => 14]));
    }

    /** An institution-scoped KPI target must not name a scope. */
    #[Test]
    public function kpi_target_scope_must_be_coherent(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('kpi_targets')->insert([
            'kpi_period_id'  => DB::table('kpi_periods')->where('code', '2026')->value('id'),
            'kpi_measure_id' => DB::table('kpi_measures')->where('code', 'PUBLICATION_COUNT')->value('id'),
            'scope_type'     => 'INSTITUTION',
            'scope_id'       => 1,           // contradicts INSTITUTION scope
            'target_value'   => 600,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /** Validation history is evidence; it must not be rewritable. */
    #[Test]
    public function submission_reviews_are_append_only(): void
    {
        $staff  = $this->staff();
        $record = $this->record($staff);

        $submission = Submission::create([
            'research_record_id' => $record->id,
            'status'             => 'APPROVED',
            'submitted_by'       => $staff->user_id,
            'submitted_at'       => now(),
        ]);

        $review = \App\Models\SubmissionReview::create([
            'submission_id'    => $submission->id,
            'revision_no'      => 1,
            'reviewer_user_id' => $staff->user_id,
            'reviewer_role'    => 'TDPP',
            'decision'         => 'APPROVED',
            'decided_at'       => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $review->update(['remarks' => 'rewriting history']);
    }

    /**
     * D1 blocker — FKAAS has 77 lecturers and no TDPP appointment, so the
     * system must be able to name faculties where nobody can validate.
     */
    #[Test]
    public function faculties_without_an_active_validator_are_queryable(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $this->assertFalse($fsktm->hasActiveValidator());

        $staff = $this->staff('tdpp@uthm.edu.my');
        DB::table('faculty_leaders')->insert([
            'faculty_id'       => $fsktm->id,
            'staff_profile_id' => $staff->id,
            'appointment'      => 'TDPP',
            'valid_from'       => now()->subYear(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->assertTrue($fsktm->fresh()->hasActiveValidator());

        $uncovered = Faculty::whereDoesntHave(
            'leaders',
            fn ($q) => $q->whereNull('valid_to')
        )->pluck('code');

        $this->assertContains('FKAAS', $uncovered->all());
        $this->assertNotContains('FSKTM', $uncovered->all());
    }
}
