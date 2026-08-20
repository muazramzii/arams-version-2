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
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The direct answer to ARAMS 1.0's central defect.
 *
 * In 1.0, 24 of 25 portal pages performed no server-side role check: any
 * authenticated lecturer could open the institution-wide admin dashboard, read
 * a colleague's full record via ?id=, or view another faculty's validation
 * queue simply by typing the URL. Every test here attempts exactly that kind
 * of access and asserts the API refuses it.
 */
class RoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function makeUser(string $role, string $email, ?Faculty $faculty = null): User
    {
        $user = User::create([
            'email' => $email, 'password' => 'correct-horse-battery', 'role' => $role, 'is_active' => true,
        ]);

        $profile = StaffProfile::create([
            'user_id' => $user->id,
            'staff_no' => 'UTH' . random_int(100000, 999999),
            'full_name' => ucfirst($role) . ' ' . $user->id,
        ]);

        if ($faculty) {
            DB::table('staff_affiliations')->insert([
                'staff_profile_id' => $profile->id, 'faculty_id' => $faculty->id,
                'valid_from' => now()->subYears(2), 'is_primary' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user->fresh();
    }

    private function appointTdpp(User $user, Faculty $faculty): void
    {
        DB::table('faculty_leaders')->insert([
            'faculty_id' => $faculty->id,
            'staff_profile_id' => $user->staffProfile->id,
            'appointment' => 'TDPP',
            'valid_from' => now()->subYear(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function submissionFor(User $owner, Faculty $faculty, string $status = 'SUBMITTED'): Submission
    {
        $record = ResearchRecord::create([
            'research_type_id' => ResearchType::where('code', 'PUBLICATION')->value('id'),
            'owner_staff_profile_id' => $owner->staffProfile->id,
            'display_title' => 'Test Paper',
            'effective_date' => '2026-02-01',
            'effective_date_precision' => 'YEAR',
        ]);

        return Submission::create([
            'research_record_id' => $record->id,
            'status' => $status,
            'submitted_by' => $owner->id,
            'faculty_id_at_submission' => $faculty->id,
            'submitted_at' => now(),
            'first_submitted_at' => now(),
        ]);
    }

    // ── Authentication ──────────────────────────────────────────────────

    #[Test]
    public function unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/submissions')->assertStatus(401);
        $this->getJson('/api/v1/research-records')->assertStatus(401);
    }

    #[Test]
    public function login_returns_a_token_and_never_echoes_the_role_the_client_asked_for(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $this->makeUser('Lecturer', 'lect@uthm.edu.my', $fsktm);

        // Client claims Admin; the account is a Lecturer.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'lect@uthm.edu.my',
            'password' => 'correct-horse-battery',
            'role' => 'Admin',
        ]);

        $response->assertOk()->assertJsonPath('data.user.role', 'Lecturer');
        $this->assertNotEmpty($response->json('data.token'));
    }

    #[Test]
    public function login_does_not_reveal_whether_an_account_exists(): void
    {
        $this->makeUser('Lecturer', 'real@uthm.edu.my');

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'real@uthm.edu.my', 'password' => 'nope',
        ]);
        $noSuchAccount = $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@uthm.edu.my', 'password' => 'nope',
        ]);

        $this->assertSame(
            $wrongPassword->json('errors.email'),
            $noSuchAccount->json('errors.email'),
        );
    }

    #[Test]
    public function an_inactive_account_cannot_sign_in(): void
    {
        $user = $this->makeUser('Lecturer', 'dormant@uthm.edu.my');
        $user->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'dormant@uthm.edu.my', 'password' => 'correct-horse-battery',
        ])->assertStatus(422);
    }

    // ── D1: only a serving TDPP validates ───────────────────────────────

    #[Test]
    public function d1_admin_cannot_approve_a_submission(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->makeUser('Lecturer', 'l1@uthm.edu.my', $fsktm);
        $admin    = $this->makeUser('Admin', 'admin@uthm.edu.my');

        $submission = $this->submissionFor($lecturer, $fsktm, 'UNDER_REVIEW');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/submissions/{$submission->id}/approve")->assertStatus(403);

        $this->assertSame('UNDER_REVIEW', $submission->fresh()->status->value);
    }

    #[Test]
    public function d1_a_lecturer_cannot_approve_anything(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->makeUser('Lecturer', 'l2@uthm.edu.my', $fsktm);
        $other    = $this->makeUser('Lecturer', 'l3@uthm.edu.my', $fsktm);

        $submission = $this->submissionFor($other, $fsktm, 'UNDER_REVIEW');

        Sanctum::actingAs($lecturer);
        $this->postJson("/api/v1/submissions/{$submission->id}/approve")->assertStatus(403);
    }

    #[Test]
    public function d1_a_tdpp_cannot_approve_another_facultys_submission(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $fkee  = Faculty::where('code', 'FKEE')->first();

        $lecturer  = $this->makeUser('Lecturer', 'l4@uthm.edu.my', $fsktm);
        $otherTdpp = $this->makeUser('TDPP', 'tdpp-fkee@uthm.edu.my', $fkee);
        $this->appointTdpp($otherTdpp, $fkee);

        $submission = $this->submissionFor($lecturer, $fsktm, 'UNDER_REVIEW');

        Sanctum::actingAs($otherTdpp);
        $this->postJson("/api/v1/submissions/{$submission->id}/approve")->assertStatus(403);
        $this->assertSame('UNDER_REVIEW', $submission->fresh()->status->value);
    }

    #[Test]
    public function d1_a_tdpp_with_no_current_appointment_cannot_approve(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->makeUser('Lecturer', 'l5@uthm.edu.my', $fsktm);
        // Role is TDPP but no faculty_leaders row exists.
        $tdpp = $this->makeUser('TDPP', 'unappointed@uthm.edu.my', $fsktm);

        $submission = $this->submissionFor($lecturer, $fsktm, 'UNDER_REVIEW');

        Sanctum::actingAs($tdpp);
        $this->postJson("/api/v1/submissions/{$submission->id}/approve")->assertStatus(403);
    }

    #[Test]
    public function d1_the_serving_tdpp_can_approve_and_the_approver_is_recorded(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->makeUser('Lecturer', 'l6@uthm.edu.my', $fsktm);
        $tdpp     = $this->makeUser('TDPP', 'tdpp-fsktm@uthm.edu.my', $fsktm);
        $this->appointTdpp($tdpp, $fsktm);

        $submission = $this->submissionFor($lecturer, $fsktm, 'UNDER_REVIEW');

        Sanctum::actingAs($tdpp);
        $this->postJson("/api/v1/submissions/{$submission->id}/approve", [
            'remarks' => 'Verified against Scopus.',
        ])->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $submission->refresh();

        // The 1.0 defect: 108 of 272 approvals recorded no approver at all.
        $this->assertSame($tdpp->id, $submission->decided_by);
        $this->assertFalse($submission->hasUnknownApprover());

        // And the decision is preserved as history, not just as current state.
        $this->assertDatabaseHas('submission_reviews', [
            'submission_id'    => $submission->id,
            'reviewer_user_id' => $tdpp->id,
            'decision'         => 'APPROVED',
        ]);
    }

    #[Test]
    public function d1_a_tdpp_cannot_review_their_own_submission(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $tdpp  = $this->makeUser('TDPP', 'tdpp-author@uthm.edu.my', $fsktm);
        $this->appointTdpp($tdpp, $fsktm);

        $own = $this->submissionFor($tdpp, $fsktm, 'UNDER_REVIEW');

        Sanctum::actingAs($tdpp);
        $this->postJson("/api/v1/submissions/{$own->id}/approve")->assertStatus(403);
    }

    #[Test]
    public function rejecting_without_remarks_is_refused(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->makeUser('Lecturer', 'l7@uthm.edu.my', $fsktm);
        $tdpp     = $this->makeUser('TDPP', 'tdpp2@uthm.edu.my', $fsktm);
        $this->appointTdpp($tdpp, $fsktm);

        $submission = $this->submissionFor($lecturer, $fsktm, 'UNDER_REVIEW');

        Sanctum::actingAs($tdpp);
        $this->postJson("/api/v1/submissions/{$submission->id}/reject", [])->assertStatus(422);
    }

    // ── Cross-tenant reads ──────────────────────────────────────────────

    #[Test]
    public function a_lecturer_cannot_read_another_lecturers_record_by_id(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $mine  = $this->makeUser('Lecturer', 'mine@uthm.edu.my', $fsktm);
        $yours = $this->makeUser('Lecturer', 'yours@uthm.edu.my', $fsktm);

        $theirSubmission = $this->submissionFor($yours, $fsktm);
        $theirRecordId   = $theirSubmission->research_record_id;

        Sanctum::actingAs($mine);
        // The exact 1.0 attack: change the id in the URL.
        $this->getJson("/api/v1/research-records/{$theirRecordId}")->assertStatus(403);
        $this->getJson("/api/v1/submissions/{$theirSubmission->id}")->assertStatus(403);
    }

    #[Test]
    public function a_lecturers_list_contains_only_their_own_records(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $mine  = $this->makeUser('Lecturer', 'mine2@uthm.edu.my', $fsktm);
        $yours = $this->makeUser('Lecturer', 'yours2@uthm.edu.my', $fsktm);

        $this->submissionFor($mine, $fsktm);
        $this->submissionFor($yours, $fsktm);

        Sanctum::actingAs($mine);
        $response = $this->getJson('/api/v1/research-records')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function a_tdpp_queue_never_includes_another_faculty(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $fkee  = Faculty::where('code', 'FKEE')->first();

        $lectA = $this->makeUser('Lecturer', 'a@uthm.edu.my', $fsktm);
        $lectB = $this->makeUser('Lecturer', 'b@uthm.edu.my', $fkee);
        $tdpp  = $this->makeUser('TDPP', 'tdpp-a@uthm.edu.my', $fsktm);
        $this->appointTdpp($tdpp, $fsktm);

        $this->submissionFor($lectA, $fsktm);
        $this->submissionFor($lectB, $fkee);

        Sanctum::actingAs($tdpp);
        $response = $this->getJson('/api/v1/submissions/queue')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($fsktm->id, $response->json('data.0.faculty_id_at_submission'));
    }

    // ── Blocker 1: faculties with no validator ──────────────────────────

    #[Test]
    public function submitting_is_refused_when_the_faculty_has_no_tdpp(): void
    {
        // FKAAS is the real case: 77 lecturers, no appointment.
        $fkaas    = Faculty::where('code', 'FKAAS')->first();
        $lecturer = $this->makeUser('Lecturer', 'fkaas@uthm.edu.my', $fkaas);

        $submission = $this->submissionFor($lecturer, $fkaas, 'DRAFT');

        Sanctum::actingAs($lecturer);
        $response = $this->postJson("/api/v1/submissions/{$submission->id}/submit")
            ->assertStatus(422);

        $this->assertStringContainsString('no TDPP appointed', $response->json('detail'));
        // Refused outright rather than parked in a queue nobody can action.
        $this->assertSame('DRAFT', $submission->fresh()->status->value);
    }

    #[Test]
    public function coverage_gaps_names_faculties_without_a_validator(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $tdpp  = $this->makeUser('TDPP', 'gap@uthm.edu.my', $fsktm);
        $this->appointTdpp($tdpp, $fsktm);

        Sanctum::actingAs($tdpp);
        $codes = collect($this->getJson('/api/v1/submissions/coverage-gaps')->json('data'))
            ->pluck('code');

        $this->assertContains('FKAAS', $codes->all());
        $this->assertNotContains('FSKTM', $codes->all());
    }
}
