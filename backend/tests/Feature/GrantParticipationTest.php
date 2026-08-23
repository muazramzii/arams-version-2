<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\GrantProject;
use App\Models\ResearchRecord;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The project/participation split, exercised through the API a lecturer
 * actually uses.
 *
 * The migration rehearsal proved the constraint catches duplicates already in
 * the ARAMS 1.0 data — eleven codes, RM 420,000 double-counted. These tests
 * check the same defect cannot be created afresh through the product.
 */
class GrantParticipationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private Faculty $faculty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faculty = Faculty::where('code', 'FSKTM')->first();
    }

    private function lecturer(string $email): User
    {
        $user = User::create([
            'email' => $email, 'password' => 'correct-horse-battery',
            'role' => 'Lecturer', 'is_active' => true,
        ]);

        $profile = StaffProfile::create([
            'user_id' => $user->id,
            'staff_no' => 'UTH' . random_int(100000, 999999),
            'full_name' => "Lecturer {$user->id}",
        ]);

        DB::table('staff_affiliations')->insert([
            'staff_profile_id' => $profile->id, 'faculty_id' => $this->faculty->id,
            'valid_from' => now()->subYear(), 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user->fresh();
    }

    private function claim(int $projectId, ?string $roleCode = 'PI'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/research-records', [
            'type'             => 'GRANT',
            'grant_project_id' => $projectId,
            'grant_role_id'    => DB::table('grant_roles')->where('code', $roleCode)->value('id'),
            'allocated_amount' => 35000,
        ]);
    }

    #[Test]
    public function a_grant_code_cannot_be_registered_twice(): void
    {
        Sanctum::actingAs($this->lecturer('a@uthm.edu.my'));

        $payload = ['grant_code' => 'Q940', 'title' => 'Shared Research Grant'];

        $this->postJson('/api/v1/grant-projects', $payload)->assertCreated();

        // The message has to tell the lecturer what to do instead, or they
        // will invent a variant code and recreate the ARAMS 1.0 problem.
        $response = $this->postJson('/api/v1/grant-projects', $payload)->assertStatus(422);
        $this->assertStringContainsString(
            'Search for it instead',
            $response->json('errors.grant_code.0'),
        );
    }

    #[Test]
    public function two_lecturers_can_share_one_grant_project(): void
    {
        $first  = $this->lecturer('first@uthm.edu.my');
        $second = $this->lecturer('second@uthm.edu.my');

        Sanctum::actingAs($first);
        $projectId = $this->postJson('/api/v1/grant-projects', [
            'grant_code'   => 'Q941',
            'title'        => 'Collaborative Grant',
            'total_amount' => 100000,
            'start_date'   => '2026-01-15',
        ])->assertCreated()->json('data.id');

        $this->claim($projectId, 'PI')->assertCreated();

        Sanctum::actingAs($second);
        $this->claim($projectId, 'MEMBER')->assertCreated();

        // Two participations, one project — the institutional value is
        // counted once, not twice.
        $this->assertSame(1, GrantProject::count());
        $this->assertSame(2, DB::table('grants')->where('grant_project_id', $projectId)->count());
        $this->assertEquals(100000, (float) GrantProject::find($projectId)->total_amount);
    }

    #[Test]
    public function one_lecturer_cannot_claim_the_same_grant_twice(): void
    {
        Sanctum::actingAs($this->lecturer('greedy@uthm.edu.my'));

        $projectId = $this->postJson('/api/v1/grant-projects', [
            'grant_code' => 'Q942', 'title' => 'Single Claim Grant',
        ])->assertCreated()->json('data.id');

        $this->claim($projectId)->assertCreated();

        // Exactly the ARAMS 1.0 pattern: all eleven duplicate codes were one
        // lecturer claiming one grant twice.
        $response = $this->claim($projectId)->assertStatus(422);

        // The refusal has to be actionable, and must not leak internals.
        $detail = $response->json('detail');
        $this->assertStringContainsString('already recorded your participation', $detail);
        $this->assertStringNotContainsString('SQL', $detail);
        $this->assertStringNotContainsString('SQLSTATE', $detail);

        $this->assertSame(1, DB::table('grants')->where('grant_project_id', $projectId)->count());
    }

    /**
     * A constraint violation that no controller anticipates must still not
     * hand the client the SQL statement, database name, host and port.
     * QueryException extends RuntimeException, so this very nearly shipped.
     */
    #[Test]
    public function a_database_constraint_violation_never_leaks_sql(): void
    {
        Sanctum::actingAs($this->lecturer('leak@uthm.edu.my'));

        $this->postJson('/api/v1/grant-projects', [
            'grant_code' => 'Q950', 'title' => 'First',
        ])->assertCreated();

        // Bypass the form-request uniqueness rule to reach the index itself.
        $response = $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION', 'title' => 'One', 'pub_year' => 2026, 'doi' => '10.1000/dup',
        ])->assertCreated();

        $second = $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION', 'title' => 'Two', 'pub_year' => 2026, 'doi' => '10.1000/dup',
        ]);

        $second->assertStatus(409)->assertJsonPath('title', 'Conflict');

        $body = $second->getContent();
        foreach (['SQLSTATE', 'insert into', 'Database:', 'Host:', 'arams2'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "response leaked: {$leak}");
        }

        unset($response);
    }

    #[Test]
    public function a_grant_with_no_start_date_is_flagged_as_undateable(): void
    {
        Sanctum::actingAs($this->lecturer('nodate@uthm.edu.my'));

        $created = $this->postJson('/api/v1/grant-projects', [
            'grant_code' => 'Q943', 'title' => 'Undated Grant',
        ])->assertCreated();

        // The picker warns before the lecturer commits, rather than the
        // record quietly vanishing from KPI later.
        $this->assertTrue($created->json('data.needs_start_date'));

        $record = $this->claim($created->json('data.id'))->assertCreated();

        $this->assertSame('UNKNOWN', $record->json('data.effective_date_precision'));
        $this->assertTrue($record->json('data.needs_date_backfill'));
    }

    #[Test]
    public function a_grant_with_a_start_date_is_dated_from_the_project(): void
    {
        Sanctum::actingAs($this->lecturer('dated@uthm.edu.my'));

        $projectId = $this->postJson('/api/v1/grant-projects', [
            'grant_code' => 'Q944', 'title' => 'Dated Grant', 'start_date' => '2025-03-01',
        ])->assertCreated()->json('data.id');

        $record = $this->claim($projectId)->assertCreated();

        // D4: the participation inherits the project's start date, so it
        // credits the period the work actually began in.
        $this->assertSame('2025-03-01', $record->json('data.effective_date'));
        $this->assertSame('DAY', $record->json('data.effective_date_precision'));
    }

    #[Test]
    public function research_income_can_link_to_a_grant_project(): void
    {
        Sanctum::actingAs($this->lecturer('income@uthm.edu.my'));

        $projectId = $this->postJson('/api/v1/grant-projects', [
            'grant_code' => 'Q945', 'title' => 'Funded Grant', 'start_date' => '2026-01-01',
        ])->assertCreated()->json('data.id');

        $record = $this->postJson('/api/v1/research-records', [
            'type'               => 'RESEARCH_INCOME',
            'source_name'        => 'Ministry of Higher Education',
            'income_category_id' => DB::table('income_categories')->where('code', 'RESEARCH_GRANT')->value('id'),
            'amount'             => 50000,
            'year_received'      => 2026,
            'grant_project_id'   => $projectId,
        ])->assertCreated();

        $this->assertDatabaseHas('research_incomes', [
            'research_record_id' => $record->json('data.id'),
            'grant_project_id'   => $projectId,
        ]);
        $this->assertSame('2026-01-01', $record->json('data.effective_date'));
    }

    #[Test]
    public function income_must_be_a_positive_amount(): void
    {
        Sanctum::actingAs($this->lecturer('zero@uthm.edu.my'));

        $this->postJson('/api/v1/research-records', [
            'type'               => 'RESEARCH_INCOME',
            'source_name'        => 'Nobody',
            'income_category_id' => DB::table('income_categories')->where('code', 'RESEARCH_GRANT')->value('id'),
            'amount'             => 0,
            'year_received'      => 2026,
        ])->assertStatus(422);
    }

    #[Test]
    public function a_publication_can_be_indexed_in_more_than_one_place(): void
    {
        Sanctum::actingAs($this->lecturer('indexed@uthm.edu.my'));

        $indexingIds = DB::table('indexings')->whereIn('code', ['SCOPUS', 'WOS'])->pluck('id')->all();

        $record = $this->postJson('/api/v1/research-records', [
            'type' => 'PUBLICATION',
            'title' => 'Multi-Indexed Paper',
            'pub_year' => 2026,
            'indexing_ids' => $indexingIds,
        ])->assertCreated();

        // ARAMS 1.0 stored this as a SET and matched it with `=`, so a paper
        // indexed 'Scopus,WoS' was invisible to a Scopus filter. As rows,
        // both filters find it.
        $this->assertSame(2, DB::table('publication_indexings')
            ->where('research_record_id', $record->json('data.id'))->count());

        $this->assertSame(1, ResearchRecord::whereHas(
            'publication',
            fn ($q) => $q->indexedIn('SCOPUS'),
        )->count());
        $this->assertSame(1, ResearchRecord::whereHas(
            'publication',
            fn ($q) => $q->indexedIn('WOS'),
        )->count());
    }

    #[Test]
    public function reference_data_comes_back_in_one_request(): void
    {
        Sanctum::actingAs($this->lecturer('refs@uthm.edu.my'));

        $this->getJson('/api/v1/reference-data')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'levels', 'categories', 'roles', 'statuses', 'funders',
                'income_categories', 'ip_types', 'publication_types',
                'author_roles', 'indexings', 'award_types', 'award_levels',
            ]]);
    }
}
