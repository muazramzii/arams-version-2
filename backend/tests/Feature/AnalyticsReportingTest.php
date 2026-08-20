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
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Analytics scoping (D5), notification routing, and reporting.
 */
class AnalyticsReportingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $role, string $email, ?Faculty $faculty = null): User
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

        if ($faculty) {
            DB::table('staff_affiliations')->insert([
                'staff_profile_id' => $profile->id, 'faculty_id' => $faculty->id,
                'valid_from' => now()->subYears(3), 'is_primary' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

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

    /** An approved, attributed publication — the countable unit. */
    private function approvedRecord(User $owner, Faculty $faculty, int $year = 2026): ResearchRecord
    {
        $record = ResearchRecord::create([
            'research_type_id' => ResearchType::where('code', 'PUBLICATION')->value('id'),
            'owner_staff_profile_id' => $owner->staffProfile->id,
            'display_title' => "Paper {$year}-" . random_int(100, 999),
            'effective_date' => "{$year}-06-01",
            'effective_date_precision' => 'YEAR',
            'attributed_faculty_id' => $faculty->id,
            'attributed_at' => now(),
            'attribution_basis' => 'EFFECTIVE_DATE',
        ]);

        DB::table('publications')->insert([
            'research_record_id' => $record->id, 'pub_year' => $year,
            'quartile' => 'Q1', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Submission::create([
            'research_record_id' => $record->id, 'status' => 'APPROVED',
            'submitted_by' => $owner->id, 'faculty_id_at_submission' => $faculty->id,
            'submitted_at' => now(), 'first_submitted_at' => now(),
            'decided_at' => now(),
        ]);

        return $record;
    }

    // ── Analytics scoping ───────────────────────────────────────────────

    #[Test]
    public function a_lecturer_overview_counts_only_their_own_output(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $mine  = $this->user('Lecturer', 'me@uthm.edu.my', $fsktm);
        $yours = $this->user('Lecturer', 'you@uthm.edu.my', $fsktm);

        $this->approvedRecord($mine, $fsktm);
        $this->approvedRecord($yours, $fsktm);
        $this->approvedRecord($yours, $fsktm);

        Sanctum::actingAs($mine);
        $this->getJson('/api/v1/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.scope', 'STAFF')
            ->assertJsonPath('data.totals.publications', 1);
    }

    #[Test]
    public function a_tdpp_overview_covers_their_faculty_and_stops_there(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $fkee  = Faculty::where('code', 'FKEE')->first();

        $lectA = $this->user('Lecturer', 'a@uthm.edu.my', $fsktm);
        $lectB = $this->user('Lecturer', 'b@uthm.edu.my', $fkee);
        $tdpp  = $this->user('TDPP', 't@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        $this->approvedRecord($lectA, $fsktm);
        $this->approvedRecord($lectA, $fsktm);
        $this->approvedRecord($lectB, $fkee);   // must not be counted

        Sanctum::actingAs($tdpp);
        $this->getJson('/api/v1/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.scope', 'FACULTY')
            ->assertJsonPath('data.totals.publications', 2);
    }

    #[Test]
    public function a_tdpp_without_an_appointment_falls_back_to_personal_scope(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $lect  = $this->user('Lecturer', 'c@uthm.edu.my', $fsktm);
        // Role is TDPP, but no faculty_leaders row.
        $tdpp = $this->user('TDPP', 'unappointed@uthm.edu.my', $fsktm);

        $this->approvedRecord($lect, $fsktm);

        Sanctum::actingAs($tdpp);
        $this->getJson('/api/v1/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.scope', 'STAFF')
            ->assertJsonPath('data.totals.publications', 0);
    }

    #[Test]
    public function an_admin_sees_the_whole_institution(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $fkee  = Faculty::where('code', 'FKEE')->first();
        $lectA = $this->user('Lecturer', 'd@uthm.edu.my', $fsktm);
        $lectB = $this->user('Lecturer', 'e@uthm.edu.my', $fkee);
        $admin = $this->user('Admin', 'admin@uthm.edu.my');

        $this->approvedRecord($lectA, $fsktm);
        $this->approvedRecord($lectB, $fkee);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.scope', 'INSTITUTION')
            ->assertJsonPath('data.totals.publications', 2);
    }

    #[Test]
    public function analytics_exclude_unapproved_and_deleted_records(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $lect  = $this->user('Lecturer', 'f@uthm.edu.my', $fsktm);

        $approved = $this->approvedRecord($lect, $fsktm);
        $deleted  = $this->approvedRecord($lect, $fsktm);
        $deleted->delete();

        // A pending record must not count either.
        $pending = ResearchRecord::create([
            'research_type_id' => ResearchType::where('code', 'PUBLICATION')->value('id'),
            'owner_staff_profile_id' => $lect->staffProfile->id,
            'display_title' => 'Not yet approved',
            'effective_date' => '2026-01-01', 'effective_date_precision' => 'YEAR',
            'attributed_faculty_id' => $fsktm->id,
        ]);
        Submission::create([
            'research_record_id' => $pending->id, 'status' => 'SUBMITTED',
            'submitted_by' => $lect->id, 'faculty_id_at_submission' => $fsktm->id,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($lect);
        $this->getJson('/api/v1/analytics/overview')
            ->assertOk()
            ->assertJsonPath('data.totals.records', 1);
    }

    #[Test]
    public function the_breakdown_dimension_must_be_whitelisted(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $lect  = $this->user('Lecturer', 'g@uthm.edu.my', $fsktm);

        Sanctum::actingAs($lect);
        // A column name is never taken from the request.
        $this->getJson('/api/v1/analytics/breakdown?dimension=users.password')
            ->assertStatus(422);
        $this->getJson('/api/v1/analytics/breakdown?dimension=quartile')->assertOk();
    }

    // ── D5: anonymised benchmarking ─────────────────────────────────────

    #[Test]
    public function d5_a_benchmark_is_suppressed_when_too_few_faculties_report(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $fkee  = Faculty::where('code', 'FKEE')->first();

        $tdpp = $this->user('TDPP', 'bench@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        $mine  = $this->user('Lecturer', 'h@uthm.edu.my', $fsktm);
        $other = $this->user('Lecturer', 'i@uthm.edu.my', $fkee);

        $this->approvedRecord($mine, $fsktm);
        $this->approvedRecord($other, $fkee);   // cohort of 1 — below the floor

        Sanctum::actingAs($tdpp);
        $response = $this->getJson("/api/v1/analytics/benchmark?faculty_id={$fsktm->id}")->assertOk();

        $response->assertJsonPath('data.suppressed', true);
        // Critically, the other faculty's exact value is never disclosed.
        $this->assertNull($response->json('data.institution_median'));
        $this->assertNull($response->json('data.comparison'));
    }

    #[Test]
    public function d5_a_benchmark_releases_a_median_once_the_cohort_is_large_enough(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $tdpp  = $this->user('TDPP', 'bench2@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        $this->approvedRecord($this->user('Lecturer', 'j@uthm.edu.my', $fsktm), $fsktm);

        // Three other reporting faculties clears the minimum cohort of 3.
        foreach (['FKEE', 'FKMP', 'FKAAB'] as $n => $code) {
            $faculty = Faculty::where('code', $code)->first();
            $this->approvedRecord($this->user('Lecturer', "k{$n}@uthm.edu.my", $faculty), $faculty);
        }

        Sanctum::actingAs($tdpp);
        $response = $this->getJson("/api/v1/analytics/benchmark?faculty_id={$fsktm->id}")->assertOk();

        $response->assertJsonPath('data.suppressed', false)
                 ->assertJsonPath('data.your_value', 1)
                 ->assertJsonPath('data.cohort_size', 3);

        // Still no per-faculty values and no faculty names.
        $this->assertArrayNotHasKey('faculties', $response->json('data'));
    }

    #[Test]
    public function d5_a_tdpp_cannot_benchmark_a_faculty_they_do_not_serve(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $fkee  = Faculty::where('code', 'FKEE')->first();

        $tdpp = $this->user('TDPP', 'bench3@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($tdpp);
        $this->getJson("/api/v1/analytics/benchmark?faculty_id={$fkee->id}")
            ->assertStatus(422)
            ->assertJsonPath('title', 'Action not allowed');
    }

    // ── Notification routing ────────────────────────────────────────────

    #[Test]
    public function a_new_submission_notifies_the_tdpp_and_not_the_admin(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->user('Lecturer', 'author@uthm.edu.my', $fsktm);
        $tdpp     = $this->user('TDPP', 'reviewer@uthm.edu.my', $fsktm);
        $admin    = $this->user('Admin', 'boss@uthm.edu.my');
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($lecturer);
        $created = $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION', 'title' => 'Routing Test', 'pub_year' => 2026,
        ])->assertCreated();

        $this->postJson("/api/v1/submissions/{$created->json('data.submission.id')}/submit")->assertOk();

        // ARAMS 1.0 did exactly the opposite: every Admin was told, the TDPP
        // never was, and only the TDPP could act.
        $this->assertDatabaseHas('notifications', [
            'notifiable_user_id' => $tdpp->id,
            'type'               => 'submission.received',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_user_id' => $admin->id,
            'type'               => 'submission.received',
        ]);
    }

    #[Test]
    public function a_decision_notifies_the_author_and_flags_whether_they_can_act(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->user('Lecturer', 'author2@uthm.edu.my', $fsktm);
        $tdpp     = $this->user('TDPP', 'reviewer2@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($lecturer);
        $created = $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION', 'title' => 'Decision Test', 'pub_year' => 2026,
        ])->assertCreated();
        $id = $created->json('data.submission.id');
        $this->postJson("/api/v1/submissions/{$id}/submit")->assertOk();

        Sanctum::actingAs($tdpp);
        $this->postJson("/api/v1/submissions/{$id}/claim")->assertOk();
        $this->postJson("/api/v1/submissions/{$id}/request-revision", [
            'remarks' => 'Please add the DOI.',
        ])->assertOk();

        Sanctum::actingAs($lecturer);
        $notifications = $this->getJson('/api/v1/notifications?unread_only=1')->assertOk();

        $revision = collect($notifications->json('data'))
            ->firstWhere('type', 'submission.revision_requested');

        $this->assertNotNull($revision);
        $this->assertTrue($revision['data']['actionable']);
        $this->assertSame('Please add the DOI.', $revision['data']['remarks']);
    }

    #[Test]
    public function a_user_cannot_mark_someone_elses_notification_read(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $a = $this->user('Lecturer', 'n1@uthm.edu.my', $fsktm);
        $b = $this->user('Lecturer', 'n2@uthm.edu.my', $fsktm);

        $id = (string) \Illuminate\Support\Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id, 'type' => 'test', 'notifiable_user_id' => $a->id,
            'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/notifications/{$id}/read")->assertStatus(404);

        $this->assertNull(DB::table('notifications')->where('id', $id)->value('read_at'));
    }

    // ── Reporting ───────────────────────────────────────────────────────

    #[Test]
    public function a_report_produces_a_scoped_csv_artifact(): void
    {
        Storage::fake('local');

        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $fkee  = Faculty::where('code', 'FKEE')->first();
        $tdpp  = $this->user('TDPP', 'rep@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        $this->approvedRecord($this->user('Lecturer', 'r1@uthm.edu.my', $fsktm), $fsktm);
        $this->approvedRecord($this->user('Lecturer', 'r2@uthm.edu.my', $fkee), $fkee);

        Sanctum::actingAs($tdpp);
        $run = $this->postJson('/api/v1/reports/runs', [
            'code' => 'PUBLICATIONS', 'format' => 'CSV',
        ])->assertCreated();

        // Scope is applied at generation, so the other faculty never appears.
        $run->assertJsonPath('data.status', 'READY')
            ->assertJsonPath('data.row_count', 1)
            ->assertJsonPath('data.scope_type', 'FACULTY');

        $this->assertNotEmpty($run->json('data.file_hash'));

        $download = $this->get("/api/v1/reports/runs/{$run->json('data.id')}/download")->assertOk();
        $body = $download->streamedContent();

        $this->assertStringContainsString('Validated (approved) records only', $body);
        $this->assertStringContainsString('FSKTM', $body);
        $this->assertStringNotContainsString('FKEE', $body);
    }

    #[Test]
    public function an_unsupported_report_format_says_so_rather_than_failing_obscurely(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $tdpp  = $this->user('TDPP', 'rep2@uthm.edu.my', $fsktm);
        $this->appoint($tdpp, $fsktm);

        Sanctum::actingAs($tdpp);
        $this->postJson('/api/v1/reports/runs', ['code' => 'PUBLICATIONS', 'format' => 'PDF'])
            ->assertStatus(422)
            ->assertJsonPath('title', 'Action not allowed');
    }

    #[Test]
    public function a_lecturer_cannot_run_an_admin_only_report(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $lect  = $this->user('Lecturer', 'r3@uthm.edu.my', $fsktm);

        Sanctum::actingAs($lect);
        $this->postJson('/api/v1/reports/runs', [
            'code' => 'INSTITUTIONAL_SUMMARY', 'format' => 'CSV',
        ])->assertStatus(403);

        // And it is not even offered to them.
        $codes = collect($this->getJson('/api/v1/reports/definitions')->json('data'))->pluck('code');
        $this->assertNotContains('INSTITUTIONAL_SUMMARY', $codes->all());
    }

    #[Test]
    public function a_lecturer_sees_only_their_own_audit_events(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $a = $this->user('Lecturer', 'au1@uthm.edu.my', $fsktm);
        $b = $this->user('Lecturer', 'au2@uthm.edu.my', $fsktm);

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION', 'title' => 'A record', 'pub_year' => 2026,
        ])->assertCreated();

        Sanctum::actingAs($b);
        $events = $this->getJson('/api/v1/audit-events')->assertOk()->json('data');

        $this->assertEmpty($events);
    }
}
