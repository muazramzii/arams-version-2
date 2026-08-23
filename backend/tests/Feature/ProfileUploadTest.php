<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The profile photo upload was the single worst defect in ARAMS 1.0: it read
 * the extension straight from the uploaded filename, trusted the client's
 * Content-Type, and wrote the result into a directory Apache served — so
 * `shell.php` sent as `image/jpeg` became executable code.
 *
 * Each test here fires that attack, or a variant of it, and asserts refusal.
 */
class ProfileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function lecturer(string $email = 'me@uthm.edu.my'): User
    {
        $faculty = Faculty::where('code', 'FSKTM')->first();

        $user = User::create([
            'email' => $email, 'password' => 'correct-horse-battery',
            'role' => 'Lecturer', 'is_active' => true,
        ]);

        $profile = StaffProfile::create([
            'user_id' => $user->id,
            'staff_no' => 'UTH' . random_int(100000, 999999),
            'full_name' => 'Dr Test Lecturer',
        ]);

        DB::table('staff_affiliations')->insert([
            'staff_profile_id' => $profile->id, 'faculty_id' => $faculty->id,
            'valid_from' => now()->subYear(), 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    /** A real PNG, produced without GD so the test runs anywhere. */
    private function realPng(string $name = 'avatar.png'): UploadedFile
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAHElEQVQ4jWNgGAWjYBSMglEw'
            . 'CkbBKBgFo2AUjAIA2ScAAeR6a7wAAAAASUVORK5CYII='
        );

        $path = tempnam(sys_get_temp_dir(), 'png') . '.png';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function phpPayload(string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'evil');
        file_put_contents($path, "<?php system(\$_GET['cmd']); ?>");

        return new UploadedFile($path, $name, $mime, null, true);
    }

    #[Test]
    public function a_real_image_is_accepted_and_served_back_safely(): void
    {
        Storage::fake('local');
        $user = $this->lecturer();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/photo', ['photo' => $this->realPng()])
            ->assertOk()
            ->assertJsonPath('data.has_photo', true);

        $stored = $user->staffProfile->fresh()->profile_photo_path;

        // Stored outside any web-served directory, under a generated name —
        // the uploaded filename never contributes a path or an extension.
        $this->assertStringStartsWith('profile-photos/', $stored);
        $this->assertStringNotContainsString('avatar', $stored);
        Storage::disk('local')->assertExists($stored);

        $response = $this->get('/api/v1/profile/photo')->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        // Stops a browser second-guessing the type we just declared.
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /** The exact ARAMS 1.0 attack. */
    #[Test]
    public function a_php_file_disguised_as_a_jpeg_is_refused(): void
    {
        Storage::fake('local');
        $user = $this->lecturer();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/profile/photo', [
            'photo' => $this->phpPayload('shell.jpg', 'image/jpeg'),
        ])->assertStatus(422);

        $this->assertStringContainsString('not the image type its name claims', $response->json('detail'));
        $this->assertNull($user->staffProfile->fresh()->profile_photo_path);
        $this->assertEmpty(Storage::disk('local')->allFiles('profile-photos'));
    }

    #[Test]
    public function a_php_extension_is_refused_outright(): void
    {
        Storage::fake('local');
        $user = $this->lecturer();
        Sanctum::actingAs($user);

        foreach (['shell.php', 'shell.phtml', 'shell.php5', 'shell.svg', 'shell.html'] as $name) {
            $this->postJson('/api/v1/profile/photo', [
                'photo' => $this->phpPayload($name, 'image/jpeg'),
            ])->assertStatus(422);
        }

        $this->assertNull($user->staffProfile->fresh()->profile_photo_path);
    }

    #[Test]
    public function a_double_extension_does_not_slip_through(): void
    {
        Storage::fake('local');
        $user = $this->lecturer();
        Sanctum::actingAs($user);

        // ARAMS 1.0 took everything after the last dot, so `shell.jpg.php`
        // produced a .php file. Here the extension is checked against a
        // whitelist AND the bytes must match it.
        $this->postJson('/api/v1/profile/photo', [
            'photo' => $this->phpPayload('shell.jpg.php', 'image/jpeg'),
        ])->assertStatus(422);

        $this->assertNull($user->staffProfile->fresh()->profile_photo_path);
    }

    #[Test]
    public function an_image_renamed_to_a_mismatched_image_extension_is_refused(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->lecturer());

        // A genuine PNG claiming to be a JPEG: harmless, but it would be
        // served with the wrong Content-Type, so it is refused.
        $png = $this->realPng('actually-a-png.jpg');

        $this->postJson('/api/v1/profile/photo', ['photo' => $png])->assertStatus(422);
    }

    #[Test]
    public function the_photo_route_never_reveals_the_stored_path(): void
    {
        Storage::fake('local');
        $user = $this->lecturer();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/photo', ['photo' => $this->realPng()])->assertOk();

        $body = $this->getJson('/api/v1/profile')->assertOk()->getContent();

        $this->assertStringContainsString('"has_photo":true', $body);
        // A path is an invitation to guess neighbouring ones.
        $this->assertStringNotContainsString('profile-photos/', $body);
        $this->assertStringNotContainsString('profile_photo_path', $body);
    }

    #[Test]
    public function a_lecturer_cannot_fetch_a_colleagues_photo(): void
    {
        Storage::fake('local');

        $owner = $this->lecturer('owner@uthm.edu.my');
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/profile/photo', ['photo' => $this->realPng()])->assertOk();

        $stranger = $this->lecturer('stranger@uthm.edu.my');
        Sanctum::actingAs($stranger);

        $this->get("/api/v1/staff/{$owner->staffProfile->id}/photo")->assertStatus(403);
    }

    #[Test]
    public function replacing_a_photo_removes_the_previous_file(): void
    {
        Storage::fake('local');
        $user = $this->lecturer();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/photo', ['photo' => $this->realPng()])->assertOk();
        $first = $user->staffProfile->fresh()->profile_photo_path;

        $this->postJson('/api/v1/profile/photo', ['photo' => $this->realPng()])->assertOk();
        $second = $user->staffProfile->fresh()->profile_photo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    // ── Profile fields ──────────────────────────────────────────────────

    #[Test]
    public function a_lecturer_can_edit_their_own_profile(): void
    {
        Sanctum::actingAs($this->lecturer());

        $this->putJson('/api/v1/profile', [
            'full_name'      => 'Dr Aisyah Rahman',
            'phone'          => '07-4537000',
            'specialisation' => 'Federated learning',
        ])->assertOk()->assertJsonPath('data.full_name', 'Dr Aisyah Rahman');
    }

    #[Test]
    public function staff_number_and_faculty_cannot_be_changed_through_the_profile(): void
    {
        $user = $this->lecturer();
        $originalStaffNo = $user->staffProfile->staff_no;

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/profile', [
            'full_name'  => 'Renamed',
            'staff_no'   => 'UTH-HACKED',
            'faculty_id' => 999,
        ])->assertOk();

        // Both are institutional facts; a transfer is an audited Admin action
        // that writes affiliation history, not a self-service edit.
        $this->assertSame($originalStaffNo, $user->staffProfile->fresh()->staff_no);
    }

    #[Test]
    public function two_researchers_cannot_claim_the_same_orcid(): void
    {
        $providerId = DB::table('external_id_providers')->where('code', 'ORCID')->value('id');

        $first = $this->lecturer('one@uthm.edu.my');
        Sanctum::actingAs($first);
        $this->putJson('/api/v1/profile/external-ids', [
            'ids' => [['provider_id' => $providerId, 'value' => '0000-0002-1825-0097']],
        ])->assertOk();

        $second = $this->lecturer('two@uthm.edu.my');
        Sanctum::actingAs($second);
        $response = $this->putJson('/api/v1/profile/external-ids', [
            'ids' => [['provider_id' => $providerId, 'value' => '0000-0002-1825-0097']],
        ])->assertStatus(422);

        $this->assertStringContainsString('already recorded against another researcher', $response->json('detail'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }
}
