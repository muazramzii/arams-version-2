<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\KpiAssignment;
use App\Models\KpiMeasure;
use App\Models\KpiPeriod;
use App\Models\KpiTarget;
use App\Models\ResearchRecord;
use App\Models\ResearchType;
use App\Models\StaffProfile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TDPP assigning KPI, which the brief lists as a TDPP responsibility.
 *
 * The interesting cases are the boundary (a TDPP cannot assign outside the
 * faculty they serve) and D4 (assigning a target credits work already done in
 * that period, rather than starting from zero).
 */
class KpiAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function staff(string $role, string $email, Faculty $faculty): User
    {
        $user = User::create([
            'email' => $email, 'password' => 'correct-horse-battery',
            'role' => $role, 'is_active' => true,
        ]);

        $profile = StaffProfile::create([
            'user_id' => $user->id,
            'staff_no' => 'UTH' . random_int(100000, 999999),
            'full_name' => "Test {$role} {$user->id}",
        ]);

        DB::table('staff_affiliations')->insert([
            'staff_profile_id' => $profile->id, 'faculty_id' => $faculty->id,
            'valid_from' => now()->subYears(2), 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function appoint(User $user, Faculty $faculty): void
    {
        DB::table('faculty_leaders')->insert([
            'faculty_id' => $faculty->id,
            'staff_profile_id' => $user->staffProfile->id,
            'appointment' => 'TDPP', 'valid_from' => now()->subYear(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function payload(StaffProfile $target, array $overrides = []): array
    {
        return array_merge([
            'staff_profile_id' => $target->id,
            'kpi_period_id'    => KpiPeriod::where('code', '2026')->value('id'),
            'kpi_measure_id'   => KpiMeasure::where('code', 'PUBLICATION_COUNT')->value('id'),
            'target_value'     => 2,
            'deadline'         => now()->addMonths(6)->toDateString(),
        ], $overrides);
    }

    /** An approved publication, attributed and dated. */
    private function approvedPublication(User $owner, Faculty $faculty, int $year, string $quartile = 'Q1'): void
    {
        $record = ResearchRecord::create([
            'research_type_id' => ResearchType::where('code', 'PUBLICATION')->value('id'),
            'owner_staff_profile_id' => $owner->staffProfile->id,
            'display_title' => "Paper {$year}-" . random_int(100, 999),
            'effective_date' => "{$year}-05-01",
            'effective_date_precision' => 'YEAR',
            'attributed_faculty_id' => $faculty->id,
            'attributed_at' => now(),
            'attribution_basis' => 'EFFECTIVE_DATE',
        ]);

        DB::table('publications')->insert([
            'research_record_id' => $record->id, 'pub_year' => $year,
            'quartile' => $quartile, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Submission::create([
            'research_record_id' => $record->id, 'status' => 'APPROVED',
            'submitted_by' => $owner->id, 'faculty_id_at_submission' => $faculty->id,
            'submitted_at' => now(), 'first_submitted_at' => now(), 'decided_at' => now(),
        ]);
    }

    #[Test]
    public function a_lecturer_cannot_reach_the_assignment_endpoints(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        Sanctum::actingAs($this->staff('Lecturer', 'l@uthm.edu.my', $fsktm));

        $this->getJson('/api/v1/kpi/assignable-staff')->assertStatus(403);
        $this->postJson('/api/v1/kpi/assign', [])->assertStatus(403);
    }

    #[Test]
    public function a_tdpp_sees_only_their_own_facultys_researchers(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $fkee  = Faculty::where('code', 'FKEE')->first();

        $mine    = $this->staff('Lecturer', 'mine@uthm.edu.my', $fsktm);
        $theirs  = $this->staff('Lecturer', 'theirs@uthm.edu.my', $fkee);
        $tdpp    = $this->staff('TDPP', 'tdpp@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($tdpp);
        $names = collect($this->getJson('/api/v1/kpi/assignable-staff')->assertOk()->json('data'))
            ->pluck('full_name');

        $this->assertTrue($names->contains($mine->staffProfile->full_name));
        $this->assertFalse($names->contains($theirs->staffProfile->full_name));
    }

    #[Test]
    public function a_tdpp_with_no_appointment_sees_nobody(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $this->staff('Lecturer', 'someone@uthm.edu.my', $fsktm);

        // TDPP role, but no faculty_leaders row.
        Sanctum::actingAs($this->staff('TDPP', 'unappointed@uthm.edu.my', $fsktm));

        $this->getJson('/api/v1/kpi/assignable-staff')->assertOk()->assertJsonCount(0, 'data');
    }

    #[Test]
    public function a_tdpp_cannot_assign_outside_their_faculty(): void
    {
        $fsktm  = Faculty::where('code', 'FSKTM')->first();
        $fkee   = Faculty::where('code', 'FKEE')->first();
        $target = $this->staff('Lecturer', 'other@uthm.edu.my', $fkee);
        $tdpp   = $this->staff('TDPP', 'tdpp2@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($tdpp);
        $this->postJson('/api/v1/kpi/assign', $this->payload($target->staffProfile))
            ->assertStatus(403);

        $this->assertSame(0, KpiAssignment::count());
    }

    #[Test]
    public function assigning_creates_a_target_and_notifies_the_lecturer(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->staff('Lecturer', 'author@uthm.edu.my', $fsktm);
        $tdpp     = $this->staff('TDPP', 'tdpp3@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($tdpp);
        $this->postJson('/api/v1/kpi/assign', $this->payload($lecturer->staffProfile, [
            'description' => 'Two papers this year',
        ]))->assertCreated();

        $this->assertSame(1, KpiAssignment::count());
        $this->assertDatabaseHas('kpi_targets', [
            'scope_type' => 'STAFF', 'scope_id' => $lecturer->staffProfile->id, 'target_value' => 2,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_user_id' => $lecturer->id, 'type' => 'kpi.assigned',
        ]);
    }

    /**
     * D4 in the place it matters most operationally: a target assigned in
     * mid-year must credit work already published that year, or every
     * assignment would silently discard the lecturer's existing output.
     */
    #[Test]
    public function assigning_credits_work_already_done_in_that_period(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->staff('Lecturer', 'prolific@uthm.edu.my', $fsktm);
        $tdpp     = $this->staff('TDPP', 'tdpp4@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        // Two 2026 papers, approved before any target existed.
        $this->approvedPublication($lecturer, $fsktm, 2026);
        $this->approvedPublication($lecturer, $fsktm, 2026);
        // And one from 2025, which must NOT count toward a 2026 target.
        $this->approvedPublication($lecturer, $fsktm, 2025);

        Sanctum::actingAs($tdpp);
        $response = $this->postJson('/api/v1/kpi/assign', $this->payload($lecturer->staffProfile, [
            'target_value' => 3,
        ]))->assertCreated();

        $assignmentId = $response->json('data.id');

        $progress = DB::table('kpi_progress')->where('kpi_assignment_id', $assignmentId)->first();
        $this->assertEquals(2.0, (float) $progress->achieved_value, 'the two 2026 papers count');

        $contributions = $this->getJson("/api/v1/kpi/assignments/{$assignmentId}/contributions")
            ->assertOk()->json('data.contributions');

        $this->assertCount(2, $contributions);
        foreach ($contributions as $contribution) {
            $this->assertStringStartsWith('2026', $contribution['counted_on']);
        }
    }

    /**
     * The ARAMS 1.0 matcher compared a SET column with `=`, so a paper indexed
     * 'Scopus,WoS' never satisfied a Scopus criterion. Criteria rows use
     * `contains`, so it does.
     */
    #[Test]
    public function an_indexing_criterion_matches_a_multi_indexed_paper(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->staff('Lecturer', 'indexed@uthm.edu.my', $fsktm);
        $tdpp     = $this->staff('TDPP', 'tdpp5@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($lecturer);
        $created = $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION', 'title' => 'Dual Indexed', 'pub_year' => 2026,
            'quartile' => 'Q1',
            'indexing_ids' => DB::table('indexings')->whereIn('code', ['SCOPUS', 'WOS'])->pluck('id')->all(),
        ])->assertCreated();

        // Approve it so it is countable.
        $record = ResearchRecord::find($created->json('data.id'));
        $record->update([
            'attributed_faculty_id' => $fsktm->id,
            'attributed_at' => now(),
            'attribution_basis' => 'EFFECTIVE_DATE',
        ]);
        $record->submission->update(['status' => 'APPROVED', 'decided_at' => now()]);

        Sanctum::actingAs($tdpp);
        $response = $this->postJson('/api/v1/kpi/assign', $this->payload($lecturer->staffProfile, [
            'target_value'  => 1,
            'quartile'      => 'Q1',
            'indexing_code' => 'SCOPUS',
        ]))->assertCreated();

        $progress = DB::table('kpi_progress')
            ->where('kpi_assignment_id', $response->json('data.id'))->first();

        $this->assertEquals(1.0, (float) $progress->achieved_value,
            'a Scopus+WoS paper must satisfy a Scopus criterion');
    }

    #[Test]
    public function reassigning_the_same_measure_updates_rather_than_duplicating(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->staff('Lecturer', 'again@uthm.edu.my', $fsktm);
        $tdpp     = $this->staff('TDPP', 'tdpp6@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($tdpp);
        $this->postJson('/api/v1/kpi/assign', $this->payload($lecturer->staffProfile, ['target_value' => 2]))
            ->assertCreated();
        $this->postJson('/api/v1/kpi/assign', $this->payload($lecturer->staffProfile, ['target_value' => 5]))
            ->assertCreated();

        $this->assertSame(1, KpiAssignment::count());
        $this->assertEquals(5.0, (float) KpiTarget::where('scope_type', 'STAFF')->first()->target_value);
    }

    #[Test]
    public function two_different_criteria_can_coexist_on_one_measure(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->staff('Lecturer', 'both@uthm.edu.my', $fsktm);
        $tdpp     = $this->staff('TDPP', 'tdpp7@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($tdpp);
        // "3 papers of any quartile" and "1 Q1 paper" are different asks and
        // must not collide on the target's unique key.
        $this->postJson('/api/v1/kpi/assign', $this->payload($lecturer->staffProfile, ['target_value' => 3]))
            ->assertCreated();
        $this->postJson('/api/v1/kpi/assign', $this->payload($lecturer->staffProfile, [
            'target_value' => 1, 'quartile' => 'Q1',
        ]))->assertCreated();

        $this->assertSame(2, KpiAssignment::count());
    }
}
