<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\FacultyLeader;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Appointing a TDPP is the only remedy when a faculty cannot validate, since
 * D1 removed the Admin fallback. These tests check that the remedy works —
 * and that granting it does not quietly hand Admin the validation power back.
 */
class AdminAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function makeUser(string $role, string $email, ?Faculty $faculty = null): User
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
                'valid_from' => now()->subYear(), 'is_primary' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user->fresh();
    }

    #[Test]
    public function only_an_admin_can_reach_the_appointment_endpoints(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();

        foreach (['Lecturer', 'TDPP'] as $role) {
            Sanctum::actingAs($this->makeUser($role, strtolower($role) . '@uthm.edu.my', $fsktm));
            $this->getJson('/api/v1/admin/faculties')->assertStatus(403);
        }
    }

    #[Test]
    public function faculties_with_staff_but_no_validator_are_flagged(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $this->makeUser('Lecturer', 'lect@uthm.edu.my', $fsktm);

        Sanctum::actingAs($this->makeUser('Admin', 'admin@uthm.edu.my'));

        $rows = collect($this->getJson('/api/v1/admin/faculties')->assertOk()->json('data'));
        $fsktmRow = $rows->firstWhere('code', 'FSKTM');

        $this->assertTrue($fsktmRow['needs_tdpp'], 'staff but no serving TDPP');
        $this->assertSame(1, $fsktmRow['staff_count']);

        // A faculty with no staff at all is not a coverage problem.
        $this->assertFalse($rows->firstWhere('code', 'JBS')['needs_tdpp']);
    }

    #[Test]
    public function appointing_a_tdpp_unblocks_submission_for_that_faculty(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->makeUser('Lecturer', 'author@uthm.edu.my', $fsktm);
        $tdpp     = $this->makeUser('TDPP', 'reviewer@uthm.edu.my', $fsktm);
        $admin    = $this->makeUser('Admin', 'admin2@uthm.edu.my');

        // Before the appointment: nobody can validate, so submitting is refused.
        Sanctum::actingAs($lecturer);
        $created = $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION', 'title' => 'Blocked Paper', 'pub_year' => 2026,
        ])->assertCreated();

        $submissionId = $created->json('data.submission.id');

        $this->postJson("/api/v1/submissions/{$submissionId}/submit")
            ->assertStatus(422)
            ->assertJsonPath('title', 'Action not allowed');

        // Admin appoints.
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/faculties/{$fsktm->id}/leaders", [
            'staff_profile_id' => $tdpp->staffProfile->id,
        ])->assertCreated();

        // After: the same submission goes through.
        Sanctum::actingAs($lecturer);
        $this->postJson("/api/v1/submissions/{$submissionId}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'SUBMITTED');
    }

    #[Test]
    public function appointing_does_not_let_the_admin_validate(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->makeUser('Lecturer', 'a@uthm.edu.my', $fsktm);
        $tdpp     = $this->makeUser('TDPP', 'b@uthm.edu.my', $fsktm);
        $admin    = $this->makeUser('Admin', 'c@uthm.edu.my');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/faculties/{$fsktm->id}/leaders", [
            'staff_profile_id' => $tdpp->staffProfile->id,
        ])->assertCreated();

        Sanctum::actingAs($lecturer);
        $created = $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION', 'title' => 'Still Not Yours', 'pub_year' => 2026,
        ])->assertCreated();
        $id = $created->json('data.submission.id');
        $this->postJson("/api/v1/submissions/{$id}/submit")->assertOk();

        // D1 is untouched by the new Admin power.
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/submissions/{$id}/claim")->assertStatus(403);
        $this->postJson("/api/v1/submissions/{$id}/approve")->assertStatus(403);
    }

    #[Test]
    public function only_a_tdpp_role_holder_can_be_appointed(): void
    {
        $fsktm    = Faculty::where('code', 'FSKTM')->first();
        $lecturer = $this->makeUser('Lecturer', 'notdpp@uthm.edu.my', $fsktm);

        Sanctum::actingAs($this->makeUser('Admin', 'admin3@uthm.edu.my'));

        $this->postJson("/api/v1/admin/faculties/{$fsktm->id}/leaders", [
            'staff_profile_id' => $lecturer->staffProfile->id,
        ])->assertStatus(422);
    }

    #[Test]
    public function ending_the_last_appointment_alerts_the_admin(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $tdpp  = $this->makeUser('TDPP', 'outgoing@uthm.edu.my', $fsktm);
        $admin = $this->makeUser('Admin', 'admin4@uthm.edu.my');

        Sanctum::actingAs($admin);
        $appointment = $this->postJson("/api/v1/admin/faculties/{$fsktm->id}/leaders", [
            'staff_profile_id' => $tdpp->staffProfile->id,
        ])->json('data.id');

        $this->deleteJson("/api/v1/admin/faculty-leaders/{$appointment}")
            ->assertOk()
            ->assertJsonPath('data.faculty_now_uncovered', true);

        // The appointment is dated, never deleted — an outgoing TDPP's past
        // decisions have to stay explicable.
        $this->assertNotNull(FacultyLeader::find($appointment)->valid_to);

        $this->assertDatabaseHas('notifications', [
            'notifiable_user_id' => $admin->id,
            'type'               => 'faculty.no_validator',
        ]);
    }

    #[Test]
    public function removing_the_tdpp_role_ends_that_persons_appointments(): void
    {
        $fsktm = Faculty::where('code', 'FSKTM')->first();
        $tdpp  = $this->makeUser('TDPP', 'demoted@uthm.edu.my', $fsktm);
        $admin = $this->makeUser('Admin', 'admin5@uthm.edu.my');

        Sanctum::actingAs($admin);
        $appointment = $this->postJson("/api/v1/admin/faculties/{$fsktm->id}/leaders", [
            'staff_profile_id' => $tdpp->staffProfile->id,
        ])->json('data.id');

        $this->putJson("/api/v1/admin/users/{$tdpp->id}/role", ['role' => 'Lecturer'])->assertOk();

        // Otherwise the faculty keeps a validator who can no longer validate.
        $this->assertNotNull(FacultyLeader::find($appointment)->valid_to);
    }

    #[Test]
    public function deactivating_a_user_revokes_their_tokens(): void
    {
        $lecturer = $this->makeUser('Lecturer', 'gone@uthm.edu.my');
        $lecturer->createToken('test');

        Sanctum::actingAs($this->makeUser('Admin', 'admin6@uthm.edu.my'));
        $this->putJson("/api/v1/admin/users/{$lecturer->id}/activation", ['is_active' => false])
            ->assertOk();

        $this->assertFalse($lecturer->fresh()->is_active);
        $this->assertSame(0, $lecturer->tokens()->count(), 'a live session must not survive');
    }

    #[Test]
    public function an_admin_cannot_deactivate_or_demote_themselves(): void
    {
        $admin = $this->makeUser('Admin', 'admin7@uthm.edu.my');
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/admin/users/{$admin->id}/activation", ['is_active' => false])
            ->assertStatus(422);
        $this->putJson("/api/v1/admin/users/{$admin->id}/role", ['role' => 'Lecturer'])
            ->assertStatus(422);
    }
}
