<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\KpiAssignment;
use App\Models\KpiContribution;
use App\Models\KpiMeasure;
use App\Models\KpiPeriod;
use App\Models\KpiProgress;
use App\Models\KpiTarget;
use App\Models\ResearchRecord;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end workflow, including the two things ARAMS 1.0 could not do:
 * recover from a rejection without creating a duplicate, and credit KPI
 * against the period the work actually belongs to.
 */
class SubmissionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $lecturer;
    private User $tdpp;
    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faculty  = Faculty::where('code', 'FSKTM')->first();
        $this->lecturer = $this->makeUser('Lecturer', 'author@uthm.edu.my');
        $this->tdpp     = $this->makeUser('TDPP', 'validator@uthm.edu.my');

        DB::table('faculty_leaders')->insert([
            'faculty_id' => $this->faculty->id,
            'staff_profile_id' => $this->tdpp->staffProfile->id,
            'appointment' => 'TDPP', 'valid_from' => now()->subYear(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeUser(string $role, string $email): User
    {
        $user = User::create([
            'email' => $email, 'password' => 'correct-horse-battery',
            'role' => $role, 'is_active' => true,
        ]);

        $profile = StaffProfile::create([
            'user_id' => $user->id,
            'staff_no' => 'UTH' . random_int(100000, 999999),
            'full_name' => "Test {$role}",
        ]);

        DB::table('staff_affiliations')->insert([
            'staff_profile_id' => $profile->id, 'faculty_id' => $this->faculty->id,
            'valid_from' => now()->subYears(3), 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function createPublication(int $year = 2026): array
    {
        Sanctum::actingAs($this->lecturer);

        $response = $this->postJson('/api/v1/research-records', [
            'type'         => 'PUBLICATION',
            'title'        => 'Hybrid Test Case Prioritisation for Software Product Lines',
            'journal_name' => 'Journal of Software Engineering',
            'pub_year'     => $year,
            'quartile'     => 'Q1',
            'doi'          => '10.1000/test.' . $year . '.' . random_int(1000, 9999),
            'indexing_ids' => DB::table('indexings')->whereIn('code', ['SCOPUS', 'WOS'])->pluck('id')->all(),
        ])->assertCreated();

        return [
            'record_id'     => $response->json('data.id'),
            'submission_id' => $response->json('data.submission.id'),
        ];
    }

    #[Test]
    public function a_new_record_starts_as_a_draft_and_is_never_auto_approved(): void
    {
        ['submission_id' => $id] = $this->createPublication();

        // ARAMS 1.0's admin_add_record.php inserted rows pre-stamped 'Approved',
        // bypassing validation entirely. That path does not exist here.
        $this->assertDatabaseHas('submissions', ['id' => $id, 'status' => 'DRAFT']);
    }

    #[Test]
    public function full_lifecycle_rejection_revision_resubmission_and_approval(): void
    {
        ['record_id' => $recordId, 'submission_id' => $id] = $this->createPublication();

        // 1. Lecturer submits.
        Sanctum::actingAs($this->lecturer);
        $this->postJson("/api/v1/submissions/{$id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'SUBMITTED');

        // 2. TDPP claims, then asks for a correction.
        Sanctum::actingAs($this->tdpp);
        $this->postJson("/api/v1/submissions/{$id}/claim")
            ->assertOk()->assertJsonPath('data.status', 'UNDER_REVIEW');

        $this->postJson("/api/v1/submissions/{$id}/request-revision", [
            'remarks' => 'DOI does not resolve. Please check and resubmit.',
        ])->assertOk()->assertJsonPath('data.status', 'REVISION_REQUESTED');

        // 3. The lecturer can edit again — this is the recovery path ARAMS 1.0
        //    lacked entirely, which is why rejected work reappeared as new
        //    records and produced 16 duplicate publications.
        Sanctum::actingAs($this->lecturer);
        $this->putJson("/api/v1/research-records/{$recordId}", [
            'title'        => 'Hybrid Test Case Prioritisation for Software Product Lines',
            'journal_name' => 'Journal of Software Engineering',
            'pub_year'     => 2026,
            'quartile'     => 'Q1',
            'doi'          => '10.1000/corrected.2026',
        ])->assertOk();

        $this->postJson("/api/v1/submissions/{$id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'SUBMITTED')
            ->assertJsonPath('data.current_revision', 2);

        // 4. Approved on the second pass.
        Sanctum::actingAs($this->tdpp);
        $this->postJson("/api/v1/submissions/{$id}/claim")->assertOk();
        $this->postJson("/api/v1/submissions/{$id}/approve", ['remarks' => 'Verified.'])
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');

        // Both decisions survive, against the revision each applied to.
        $this->assertDatabaseHas('submission_reviews', [
            'submission_id' => $id, 'revision_no' => 1, 'decision' => 'REVISION_REQUESTED',
        ]);
        $this->assertDatabaseHas('submission_reviews', [
            'submission_id' => $id, 'revision_no' => 2, 'decision' => 'APPROVED',
        ]);
        $this->assertSame(2, DB::table('submission_revisions')->where('submission_id', $id)->count());

        // Attribution frozen at approval, resolved from affiliation.
        $record = ResearchRecord::find($recordId);
        $this->assertSame($this->faculty->id, $record->attributed_faculty_id);
        $this->assertSame('EFFECTIVE_DATE', $record->attribution_basis->value);
    }

    #[Test]
    public function an_owner_cannot_edit_a_record_while_it_is_under_review(): void
    {
        ['record_id' => $recordId, 'submission_id' => $id] = $this->createPublication();

        Sanctum::actingAs($this->lecturer);
        $this->postJson("/api/v1/submissions/{$id}/submit")->assertOk();

        Sanctum::actingAs($this->tdpp);
        $this->postJson("/api/v1/submissions/{$id}/claim")->assertOk();

        Sanctum::actingAs($this->lecturer);
        $this->putJson("/api/v1/research-records/{$recordId}", [
            'title' => 'Sneaky edit mid-review', 'pub_year' => 2026,
        ])->assertStatus(403);
    }

    #[Test]
    public function an_approved_submission_cannot_be_approved_twice(): void
    {
        ['submission_id' => $id] = $this->createPublication();

        Sanctum::actingAs($this->lecturer);
        $this->postJson("/api/v1/submissions/{$id}/submit")->assertOk();

        Sanctum::actingAs($this->tdpp);
        $this->postJson("/api/v1/submissions/{$id}/claim")->assertOk();
        $this->postJson("/api/v1/submissions/{$id}/approve")->assertOk();

        // ARAMS 1.0 would happily re-approve, overwriting remarks and
        // validated_at with no trace that it had happened.
        $this->postJson("/api/v1/submissions/{$id}/approve")->assertStatus(403);
        $this->assertSame(1, DB::table('submission_reviews')->where('submission_id', $id)->count());
    }

    // ── D4: credit follows the record's effective date ──────────────────

    private function makeTarget(string $periodCode, float $value = 2): KpiTarget
    {
        return KpiTarget::create([
            'kpi_period_id'  => KpiPeriod::where('code', $periodCode)->value('id'),
            'kpi_measure_id' => KpiMeasure::where('code', 'PUBLICATION_COUNT')->value('id'),
            'scope_type'     => 'STAFF',
            'scope_id'       => $this->lecturer->staffProfile->id,
            'target_value'   => $value,
        ]);
    }

    private function approve(int $submissionId): void
    {
        Sanctum::actingAs($this->lecturer);
        $this->postJson("/api/v1/submissions/{$submissionId}/submit")->assertOk();
        Sanctum::actingAs($this->tdpp);
        $this->postJson("/api/v1/submissions/{$submissionId}/claim")->assertOk();
        $this->postJson("/api/v1/submissions/{$submissionId}/approve")->assertOk();
    }

    #[Test]
    public function d4_credit_lands_in_the_period_of_the_publication_year_not_the_approval_date(): void
    {
        // Work published in 2025, approved now (2026).
        $target2025 = $this->makeTarget('2025');
        $target2026 = $this->makeTarget('2026');

        ['submission_id' => $id] = $this->createPublication(2025);
        $this->approve($id);

        $this->assertSame(1, KpiContribution::where('kpi_target_id', $target2025->id)->count(),
            '2025 publication must credit the 2025 period');
        $this->assertSame(0, KpiContribution::where('kpi_target_id', $target2026->id)->count(),
            'approval date must not decide the period');
    }

    #[Test]
    public function d4_progress_is_derived_and_falls_again_when_a_record_is_deleted(): void
    {
        $target = $this->makeTarget('2026', value: 2);

        KpiAssignment::create([
            'kpi_target_id'   => $target->id,
            'staff_profile_id' => $this->lecturer->staffProfile->id,
            'assigned_at'     => now(),
            'deadline'        => now()->addYear(),
            'status'          => 'OPEN',
        ]);

        ['record_id' => $recordId, 'submission_id' => $id] = $this->createPublication(2026);
        $this->approve($id);

        $progress = KpiProgress::where('kpi_target_id', $target->id)->whereNull('kpi_assignment_id')->first();
        $this->assertEquals(1.0, (float) $progress->achieved_value);
        $this->assertEquals(50.0, (float) $progress->percentage);

        // Admin removes the record. ARAMS 1.0's progress counter could only
        // ever rise, so a withdrawn record left the target satisfied forever.
        $admin = User::create([
            'email' => 'admin@uthm.edu.my', 'password' => 'correct-horse-battery',
            'role' => 'Admin', 'is_active' => true,
        ]);
        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/research-records/{$recordId}", [
            'reason' => 'Duplicate of an earlier submission.',
        ])->assertOk();

        $progress->refresh();
        $this->assertEquals(0.0, (float) $progress->achieved_value, 'credit must be withdrawn');
        $this->assertSame(0, KpiContribution::where('research_record_id', $recordId)->count());
    }

    #[Test]
    public function d4_a_record_with_an_unknown_effective_date_earns_no_period_credit(): void
    {
        $target = $this->makeTarget('2026');

        // An IP record with no filing or grant date — the state all 18 of the
        // 1.0 IP records migrate in.
        Sanctum::actingAs($this->lecturer);
        $created = $this->postJson('/api/v1/research-records', [
            'type'       => 'IP_RECORD',
            'title'      => 'A Patent With No Dates',
            'ip_type_id' => DB::table('ip_types')->where('code', 'PATENT')->value('id'),
        ])->assertCreated();

        $this->assertSame('UNKNOWN', $created->json('data.effective_date_precision'));
        $this->assertTrue($created->json('data.needs_date_backfill'));

        $this->approve($created->json('data.submission.id'));

        // Counted as a record, but not placeable in a period.
        $this->assertSame(0, KpiContribution::where('kpi_target_id', $target->id)->count());
        $this->assertSame(1, ResearchRecord::whereNotNull('attributed_faculty_id')->count());
    }
}
