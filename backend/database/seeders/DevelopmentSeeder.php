<?php

namespace Database\Seeders;

use App\Models\Faculty;
use App\Models\ResearchRecord;
use App\Models\ResearchType;
use App\Models\StaffProfile;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Local development accounts and sample records.
 *
 * NEVER run in production — DatabaseSeeder does not call it. Invoke it
 * explicitly with `php artisan db:seed --class=DevelopmentSeeder`.
 *
 * The passwords here are long and obviously fake. ARAMS 1.0 printed working
 * credentials on its own login page (index.php lines 118-122), which is how
 * `admin.tncpi@uthm.edu.my / password` ended up in a public repository.
 */
class DevelopmentSeeder extends Seeder
{
    private const PASSWORD = 'arams-local-dev-only';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('DevelopmentSeeder refuses to run in production.');

            return;
        }

        $fsktm = Faculty::where('code', 'FSKTM')->firstOrFail();
        $fkee  = Faculty::where('code', 'FKEE')->firstOrFail();

        $lecturer = $this->makeUser('lecturer@uthm.edu.my', 'Lecturer', 'Dr. Aisyah Rahman', 'UTH-DEV001', $fsktm);
        $second   = $this->makeUser('lecturer2@uthm.edu.my', 'Lecturer', 'Dr. Faizal Osman', 'UTH-DEV002', $fsktm);
        $tdpp     = $this->makeUser('tdpp@uthm.edu.my', 'TDPP', 'Prof. Madya Dr. Halim Yusof', 'UTH-DEV010', $fsktm);
        $tdppFkee = $this->makeUser('tdpp.fkee@uthm.edu.my', 'TDPP', 'Dr. Suriani Kamal', 'UTH-DEV011', $fkee);
        $this->makeUser('admin@uthm.edu.my', 'Admin', 'ARAMS Administrator', 'UTH-DEV020', null);

        $this->appoint($tdpp, $fsktm);
        $this->appoint($tdppFkee, $fkee);

        // A spread of states, so every UI branch has something to render.
        $this->publication($lecturer, $fsktm, 'Deep Learning for Rainfall Prediction in Johor', 2026, 'Q1', 'DRAFT');
        $this->publication($lecturer, $fsktm, 'Federated Learning on Edge Devices', 2025, 'Q2', 'SUBMITTED');
        $this->publication($lecturer, $fsktm, 'A Survey of Test Case Prioritisation', 2024, 'Q3', 'APPROVED');
        $this->publication($second,   $fsktm, 'Blockchain for Academic Credentialing', 2026, 'Q1', 'SUBMITTED');
        $this->publication($second,   $fsktm, 'Sensor Fusion for Precision Agriculture', 2023, 'Q2', 'APPROVED');

        // An IP record with no dates — the state all 18 ARAMS 1.0 IP records
        // migrate in, so the backfill worklist has something real in it.
        $this->ipRecord($lecturer, $fsktm, 'Adaptive Irrigation Controller');

        $this->command->info('Development accounts created. Password for all: ' . self::PASSWORD);
    }

    private function makeUser(string $email, string $role, string $name, string $staffNo, ?Faculty $faculty): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['password' => Hash::make(self::PASSWORD), 'role' => $role, 'is_active' => true],
        );

        $profile = StaffProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['staff_no' => $staffNo, 'full_name' => $name],
        );

        if ($faculty && ! $profile->affiliations()->exists()) {
            DB::table('staff_affiliations')->insert([
                'staff_profile_id' => $profile->id,
                'faculty_id'       => $faculty->id,
                'valid_from'       => now()->subYears(3),
                'is_primary'       => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        return $user->fresh();
    }

    private function appoint(User $user, Faculty $faculty): void
    {
        $exists = DB::table('faculty_leaders')
            ->where('faculty_id', $faculty->id)
            ->where('staff_profile_id', $user->staffProfile->id)
            ->whereNull('valid_to')
            ->exists();

        if (! $exists) {
            DB::table('faculty_leaders')->insert([
                'faculty_id'       => $faculty->id,
                'staff_profile_id' => $user->staffProfile->id,
                'appointment'      => 'TDPP',
                'valid_from'       => now()->subYear(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    private function publication(User $owner, Faculty $faculty, string $title, int $year, string $quartile, string $status): void
    {
        if (ResearchRecord::where('display_title', $title)->exists()) {
            return;
        }

        $approved = $status === 'APPROVED';

        $record = ResearchRecord::create([
            'research_type_id'         => ResearchType::where('code', 'PUBLICATION')->value('id'),
            'owner_staff_profile_id'   => $owner->staffProfile->id,
            'display_title'            => $title,
            'effective_date'           => "{$year}-01-01",
            'effective_date_precision' => 'YEAR',
            'attributed_faculty_id'    => $approved ? $faculty->id : null,
            'attributed_at'            => $approved ? now() : null,
            'attribution_basis'        => $approved ? 'EFFECTIVE_DATE' : null,
        ]);

        DB::table('publications')->insert([
            'research_record_id' => $record->id,
            'journal_name'       => 'Journal of Applied Computing',
            'pub_year'           => $year,
            'quartile'           => $quartile,
            'doi'                => '10.1000/dev.' . $record->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $submission = Submission::create([
            'research_record_id'       => $record->id,
            'status'                   => $status,
            'submitted_by'             => $owner->id,
            'faculty_id_at_submission' => $faculty->id,
            'submitted_at'             => $status === 'DRAFT' ? null : now()->subDays(random_int(1, 20)),
            'first_submitted_at'       => $status === 'DRAFT' ? null : now()->subDays(random_int(1, 20)),
            'decided_at'               => $approved ? now()->subDays(1) : null,
        ]);

        if ($approved) {
            DB::table('submission_reviews')->insert([
                'submission_id'    => $submission->id,
                'revision_no'      => 1,
                'reviewer_user_id' => null,
                'reviewer_role'    => 'TDPP',
                'decision'         => 'APPROVED',
                'remarks'          => 'Verified against Scopus.',
                'decided_at'       => now()->subDays(1),
                'origin'           => 'ARAMS_2',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    private function ipRecord(User $owner, Faculty $faculty, string $title): void
    {
        if (ResearchRecord::where('display_title', $title)->exists()) {
            return;
        }

        $record = ResearchRecord::create([
            'research_type_id'         => ResearchType::where('code', 'IP_RECORD')->value('id'),
            'owner_staff_profile_id'   => $owner->staffProfile->id,
            'display_title'            => $title,
            'effective_date'           => null,
            'effective_date_precision' => 'UNKNOWN',
        ]);

        DB::table('ip_records')->insert([
            'research_record_id' => $record->id,
            'ip_type_id'         => DB::table('ip_types')->where('code', 'PATENT')->value('id'),
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        Submission::create([
            'research_record_id'       => $record->id,
            'status'                   => 'DRAFT',
            'submitted_by'             => $owner->id,
            'faculty_id_at_submission' => $faculty->id,
        ]);
    }
}
