<?php

namespace App\Services\Legacy;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Migrates ARAMS 1.0 into ARAMS 2.0.
 *
 * Runs inside a transaction that is rolled back unless committing, so a dry
 * run exercises every insert and constraint for real and then leaves nothing
 * behind. A migration that has only been reasoned about is not a rehearsal.
 *
 * The locked decisions shape the transformation:
 *   D1  reviewer role recorded as ADMIN_LEGACY where 1.0 credited an admin
 *   D2  H-Index leaves the workflow entirely and becomes a metric snapshot
 *   D3  bundled submissions are split, one research record each
 *   D4  effective dates derived per type; unknown is declared, never guessed
 */
class LegacyMigrator
{
    private array $report = [];
    private array $idMap = [];

    public function __construct(private readonly VocabularyMapper $vocab) {}

    public function run(bool $commit, ?callable $progress = null): array
    {
        $say = $progress ?? fn () => null;

        DB::beginTransaction();

        try {
            $this->report['source'] = $this->sourceCounts();

            $say('Users and staff profiles');
            $this->migrateUsers();

            $say('Affiliations and faculty transfers');
            $this->migrateAffiliations();

            $say('TDPP appointments');
            $this->migrateAppointments();

            $say('Grant projects (deduplicating shared codes)');
            $this->migrateGrantProjects();

            $say('Research records, splitting bundled submissions');
            $this->migrateResearchRecords();

            $say('H-Index snapshots (outside the workflow, per D2)');
            $this->migrateHindex();

            $say('Awards');
            $this->migrateAwards();

            $say('KPI targets and assignments');
            $this->migrateKpi();

            $say('Audit history');
            $this->migrateAudit();

            $this->vocab->persist();

            $say('Reconciling');
            $this->reconcile();

            if ($commit) {
                DB::commit();
                $this->report['committed'] = true;
            } else {
                DB::rollBack();
                $this->report['committed'] = false;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->report;
    }

    /* ───────────────────────────── source ───────────────────────────── */

    private function legacy(string $table)
    {
        return DB::connection('legacy')->table($table);
    }

    private function sourceCounts(): array
    {
        return [
            'users'         => $this->legacy('tbl_user')->count(),
            'lecturers'     => $this->legacy('tbl_lecturer')->count(),
            'tdpp'          => $this->legacy('tbl_tdpp')->count(),
            'admins'        => $this->legacy('tbl_admin')->count(),
            'research_data' => $this->legacy('tbl_research_data')->count(),
            'publications'  => $this->legacy('tbl_publication')->count(),
            'grants'        => $this->legacy('tbl_grant')->count(),
            'hindex'        => $this->legacy('tbl_hindex')->count(),
            'ip_records'    => $this->legacy('tbl_ip_record')->count(),
            'incomes'       => $this->legacy('tbl_research_income')->count(),
            'awards'        => $this->legacy('tbl_award')->count(),
            'kpi_targets'   => $this->legacy('tbl_kpi_target')->count(),
            'kpi_tasks'     => $this->legacy('tbl_kpi_task')->count(),
            'audit'         => $this->legacy('tbl_audit_log')->count(),
        ];
    }

    /* ────────────────────────── users & staff ────────────────────────── */

    private function migrateUsers(): void
    {
        $lecturers = $this->legacy('tbl_lecturer')->get()->keyBy('user_id');
        $tdpps     = $this->legacy('tbl_tdpp')->get()->keyBy('user_id');
        $admins    = $this->legacy('tbl_admin')->get()->keyBy('user_id');

        $archived = 0;

        foreach ($this->legacy('tbl_user')->orderBy('user_id')->get() as $legacyUser) {
            $userId = DB::table('users')->insertGetId([
                'email'          => $legacyUser->email,
                // bcrypt hashes carry over unchanged; nobody's password changes.
                'password'       => $legacyUser->password,
                'role'           => $legacyUser->role,
                'is_active'      => (bool) $legacyUser->is_active,
                'last_login_at'  => $legacyUser->last_login,
                'created_at'     => $legacyUser->created_at,
                'updated_at'     => now(),
                'legacy_id'      => $legacyUser->user_id,
                'legacy_source'  => 'tbl_user',
            ]);

            $this->idMap['user'][$legacyUser->user_id] = $userId;

            $lecturer = $lecturers[$legacyUser->user_id] ?? null;
            $tdpp     = $tdpps[$legacyUser->user_id] ?? null;
            $admin    = $admins[$legacyUser->user_id] ?? null;

            $name = $lecturer->full_name ?? $tdpp->full_name ?? $admin->name ?? $legacyUser->email;

            // The 77 FKAAS shell accounts arrive archived: present so their
            // research keeps an owner, excluded from per-capita denominators
            // until a real person activates them.
            $isArchived = ! $legacyUser->is_active && $lecturer !== null;
            $archived += $isArchived ? 1 : 0;

            $profileId = DB::table('staff_profiles')->insertGetId([
                'user_id'              => $userId,
                'staff_no'             => $lecturer->staff_no ?? ('LEGACY-' . $legacyUser->user_id),
                'full_name'            => $name,
                'title'                => $lecturer->title ?? null,
                'position_id'          => $this->vocab->id('positions', $lecturer->position ?? null),
                'researcher_status_id' => $this->vocab->id('researcher_statuses', $lecturer->status_researcher ?? null),
                'phone'                => $lecturer->phone ?? $tdpp->phone ?? null,
                'specialisation'       => $lecturer->specialisation ?? null,
                'managerial_position'  => (bool) ($lecturer->managerial_position ?? false),
                'cv_url'               => $lecturer->cv_url ?? null,
                'is_archived'          => $isArchived,
                'created_at'           => now(),
                'updated_at'           => now(),
                'legacy_id'            => $lecturer->lecturer_id ?? $tdpp->tdpp_id ?? $admin->admin_id ?? null,
                'legacy_source'        => $lecturer ? 'tbl_lecturer' : ($tdpp ? 'tbl_tdpp' : 'tbl_admin'),
            ]);

            if ($lecturer) {
                $this->idMap['lecturer'][$lecturer->lecturer_id] = $profileId;
                $this->migrateExternalIds($profileId, $lecturer);
            }
            if ($tdpp) {
                $this->idMap['tdpp'][$tdpp->tdpp_id] = $profileId;
            }
        }

        $this->report['users'] = [
            'migrated'         => count($this->idMap['user'] ?? []),
            'staff_profiles'   => DB::table('staff_profiles')->count(),
            'archived_shells'  => $archived,
        ];
    }

    /** Five fixed columns in 1.0 become rows, so new providers cost nothing. */
    private function migrateExternalIds(int $profileId, object $lecturer): void
    {
        $providers = [
            'SCOPUS'         => $lecturer->scopus_id ?? null,
            'ORCID'          => $lecturer->orcid_id ?? null,
            'RESEARCHER_ID'  => $lecturer->researcher_id ?? null,
            'LENS'           => $lecturer->lens_id ?? null,
            'GOOGLE_SCHOLAR' => $lecturer->google_scholar ?? null,
        ];

        $providerIds = DB::table('external_id_providers')->pluck('id', 'code');

        foreach ($providers as $code => $value) {
            if (trim((string) $value) === '') {
                continue;
            }

            // Two staff claiming one ORCID is a real risk; the unique index
            // catches it, and insertOrIgnore keeps the run going so the whole
            // set can be reported at the end rather than failing on the first.
            DB::table('staff_external_ids')->insertOrIgnore([
                'staff_profile_id'        => $profileId,
                'external_id_provider_id' => $providerIds[$code],
                'value'                   => trim($value),
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }
    }

    /* ───────────────────────── affiliations ───────────────────────── */

    private function migrateAffiliations(): void
    {
        $faculties = DB::table('faculties')->pluck('id', 'code');
        $legacyFaculties = $this->legacy('tbl_faculty')->pluck('faculty_code', 'faculty_id');

        $resolve = fn ($legacyFacultyId) => $faculties[$legacyFaculties[$legacyFacultyId] ?? ''] ?? null;

        // One open affiliation per lecturer, dated well before any record so
        // historical attribution can resolve.
        foreach ($this->legacy('tbl_lecturer')->get() as $lecturer) {
            $profileId = $this->idMap['lecturer'][$lecturer->lecturer_id] ?? null;
            $facultyId = $resolve($lecturer->faculty_id);

            if (! $profileId || ! $facultyId) {
                continue;
            }

            DB::table('staff_affiliations')->insert([
                'staff_profile_id' => $profileId,
                'faculty_id'       => $facultyId,
                'valid_from'       => '2000-01-01',
                'valid_to'         => null,
                'is_primary'       => true,
                'transfer_reason'  => 'Initial affiliation migrated from ARAMS 1.0',
                'created_at'       => now(),
                'updated_at'       => now(),
                'legacy_id'        => $lecturer->lecturer_id,
                'legacy_source'    => 'tbl_lecturer',
            ]);
        }

        foreach ($this->legacy('tbl_tdpp')->get() as $tdpp) {
            $profileId = $this->idMap['tdpp'][$tdpp->tdpp_id] ?? null;
            $facultyId = $resolve($tdpp->faculty_id);

            if ($profileId && $facultyId) {
                DB::table('staff_affiliations')->insert([
                    'staff_profile_id' => $profileId,
                    'faculty_id'       => $facultyId,
                    'valid_from'       => '2000-01-01',
                    'is_primary'       => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                    'legacy_id'        => $tdpp->tdpp_id,
                    'legacy_source'    => 'tbl_tdpp',
                ]);
            }
        }

        /**
         * Replay tbl_faculty_transfer to reconstruct history. This is the
         * feature ARAMS 1.0 lost — four rows survive with no code that writes
         * them — and replaying it is what stops one lecturer's 37 records
         * staying wrongly attributed after her move from FSKTM to FKAAB.
         */
        $replayed = 0;

        foreach ($this->legacy('tbl_faculty_transfer')->orderBy('transferred_at')->get() as $transfer) {
            $profileId = $this->idMap['lecturer'][$transfer->lecturer_id] ?? null;
            $toFaculty = $resolve($transfer->to_faculty_id);

            if (! $profileId || ! $toFaculty) {
                continue;
            }

            $movedOn = Carbon::parse($transfer->transferred_at)->toDateString();

            DB::table('staff_affiliations')
                ->where('staff_profile_id', $profileId)
                ->whereNull('valid_to')
                ->update(['valid_to' => $movedOn, 'updated_at' => now()]);

            DB::table('staff_affiliations')->insert([
                'staff_profile_id' => $profileId,
                'faculty_id'       => $toFaculty,
                'valid_from'       => $movedOn,
                'valid_to'         => null,
                'is_primary'       => true,
                'transfer_reason'  => $transfer->remarks ?: 'Faculty transfer migrated from ARAMS 1.0',
                'created_at'       => now(),
                'updated_at'       => now(),
                'legacy_id'        => $transfer->transfer_id,
                'legacy_source'    => 'tbl_faculty_transfer',
            ]);

            $replayed++;
        }

        $this->report['affiliations'] = [
            'total'              => DB::table('staff_affiliations')->count(),
            'transfers_replayed' => $replayed,
        ];
    }

    private function migrateAppointments(): void
    {
        $faculties = DB::table('faculties')->pluck('id', 'code');
        $legacyFaculties = $this->legacy('tbl_faculty')->pluck('faculty_code', 'faculty_id');

        foreach ($this->legacy('tbl_tdpp')->get() as $tdpp) {
            $profileId = $this->idMap['tdpp'][$tdpp->tdpp_id] ?? null;
            $facultyId = $faculties[$legacyFaculties[$tdpp->faculty_id] ?? ''] ?? null;

            if ($profileId && $facultyId) {
                DB::table('faculty_leaders')->insert([
                    'faculty_id'       => $facultyId,
                    'staff_profile_id' => $profileId,
                    'appointment'      => 'TDPP',
                    'valid_from'       => Carbon::parse($tdpp->created_at)->toDateString(),
                    'valid_to'         => null,
                    'note'             => 'Appointment migrated from ARAMS 1.0',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                    'legacy_id'        => $tdpp->tdpp_id,
                    'legacy_source'    => 'tbl_tdpp',
                ]);
            }
        }

        // Faculties left with nobody who can validate. Under D1 there is no
        // Admin fallback, so this list is an operational blocker, not a note.
        $uncovered = DB::table('faculties')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('faculty_leaders')
                ->whereColumn('faculty_leaders.faculty_id', 'faculties.id')
                ->whereNull('valid_to'))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('staff_affiliations')
                ->whereColumn('staff_affiliations.faculty_id', 'faculties.id')
                ->whereNull('valid_to'))
            ->pluck('code')
            ->all();

        $this->report['appointments'] = [
            'created'                        => DB::table('faculty_leaders')->count(),
            'faculties_with_staff_no_tdpp'   => $uncovered,
        ];
    }

    /* ────────────────────── grants: project vs claim ────────────────────── */

    private function migrateGrantProjects(): void
    {
        $grants = $this->legacy('tbl_grant')->orderBy('grant_id')->get();

        // One project per distinct code. Rows with no code cannot be shared,
        // so each becomes its own project keyed on its legacy id.
        $byCode = $grants->groupBy(fn ($g) => trim((string) $g->grant_code) ?: 'NOCODE-' . $g->grant_id);
        $deduplicated = 0;

        foreach ($byCode as $code => $rows) {
            $first = $rows->first();

            if ($rows->count() > 1) {
                $deduplicated += $rows->count() - 1;
            }

            $projectId = DB::table('grant_projects')->insertGetId([
                'grant_code'        => $code,
                'title'             => $first->grant_title,
                'grant_category_id' => null,
                'grant_level_id'    => $this->vocab->id('grant_levels', $first->grant_level),
                'grant_status_id'   => $this->vocab->id('grant_statuses', $first->status),
                // The shared project carries the award value once, instead of
                // once per participant — which is what triple-counted funding.
                'total_amount'      => $first->amount,
                'currency'          => 'MYR',
                'start_date'        => $first->start_date,
                'end_date'          => $first->end_date,
                'mygrants_id'       => $first->mygrants_id,
                'created_at'        => now(),
                'updated_at'        => now(),
                'legacy_id'         => $first->grant_id,
                'legacy_source'     => 'tbl_grant',
            ]);

            foreach ($rows as $row) {
                $this->idMap['grant_project'][$row->grant_id] = $projectId;
            }
        }

        $legacyTotal = (float) $grants->sum('amount');
        $newTotal    = (float) DB::table('grant_projects')->sum('total_amount');

        $this->report['grants'] = [
            'legacy_rows'          => $grants->count(),
            'projects_created'     => DB::table('grant_projects')->count(),
            'duplicate_claims'     => $deduplicated,
            'legacy_total_value'   => round($legacyTotal, 2),
            'deduplicated_value'   => round($newTotal, 2),
            'value_difference'     => round($legacyTotal - $newTotal, 2),
        ];
    }

    /* ──────────────────── research records & submissions ──────────────────── */

    private function migrateResearchRecords(): void
    {
        $types = DB::table('research_types')->pluck('id', 'code');
        $faculties = DB::table('faculties')->pluck('id', 'code');
        $legacyFaculties = $this->legacy('tbl_faculty')->pluck('faculty_code', 'faculty_id');

        $parents = $this->legacy('tbl_research_data')->orderBy('data_id')->get()->keyBy('data_id');

        $children = [
            'PUBLICATION'     => $this->legacy('tbl_publication')->get()->groupBy('data_id'),
            'GRANT'           => $this->legacy('tbl_grant')->get()->groupBy('data_id'),
            'IP_RECORD'       => $this->legacy('tbl_ip_record')->get()->groupBy('data_id'),
            'RESEARCH_INCOME' => $this->legacy('tbl_research_income')->get()->groupBy('data_id'),
        ];

        $counts = ['PUBLICATION' => 0, 'GRANT' => 0, 'IP_RECORD' => 0, 'RESEARCH_INCOME' => 0];
        $bundlesSplit = 0;
        $unknownDates = 0;
        $unknownApprovers = 0;

        foreach ($parents as $dataId => $parent) {
            $childCount = 0;
            foreach ($children as $rows) {
                $childCount += ($rows[$dataId] ?? collect())->count();
            }

            // D3: a parent holding several records becomes several submissions.
            if ($childCount > 1) {
                $bundlesSplit++;
            }

            foreach ($children as $typeCode => $rows) {
                foreach ($rows[$dataId] ?? [] as $child) {
                    [$effectiveDate, $precision] = $this->effectiveDate($typeCode, $child);

                    if ($precision === 'UNKNOWN') {
                        $unknownDates++;
                    }

                    $ownerProfileId = $this->idMap['lecturer'][$parent->lecturer_id] ?? null;

                    if (! $ownerProfileId) {
                        continue;
                    }

                    /**
                     * Collapse duplicate grant claims.
                     *
                     * Deduplicating projects is only half the job: the same
                     * lecturer holds two rows for the same code eleven times in
                     * the 1.0 data, which would produce two participations in
                     * one project and violate uq_grant_project_owner. The
                     * second claim is dropped and reported — it was never a
                     * second grant, only a second row.
                     */
                    if ($typeCode === 'GRANT') {
                        $projectId = $this->idMap['grant_project'][$child->grant_id] ?? null;
                        $claimKey  = "{$projectId}-{$ownerProfileId}";

                        if (isset($this->claimedGrants[$claimKey])) {
                            $this->report['collapsed_grant_claims'][] = [
                                'grant_code'       => trim((string) $child->grant_code) ?: null,
                                'legacy_grant_id'  => $child->grant_id,
                                'legacy_data_id'   => $dataId,
                                'staff_profile_id' => $ownerProfileId,
                                'amount'           => (float) $child->amount,
                            ];

                            continue;
                        }

                        $this->claimedGrants[$claimKey] = true;
                    }

                    $approved = $parent->status === 'Approved';

                    // The snapshot column 1.0 wrote but never read is finally
                    // the authority for historical attribution.
                    $attributedFaculty = $approved
                        ? ($faculties[$legacyFaculties[$parent->faculty_id] ?? ''] ?? null)
                        : null;

                    $recordId = DB::table('research_records')->insertGetId([
                        'research_type_id'         => $types[$typeCode],
                        'owner_staff_profile_id'   => $ownerProfileId,
                        'display_title'            => $this->title($typeCode, $child),
                        'effective_date'           => $effectiveDate,
                        'effective_date_precision' => $precision,
                        'attributed_faculty_id'    => $attributedFaculty,
                        'attributed_at'            => $approved ? ($parent->validated_at ?? now()) : null,
                        'attribution_basis'        => $approved
                            ? ($precision === 'UNKNOWN' ? 'SUBMISSION_DATE_FALLBACK' : 'EFFECTIVE_DATE')
                            : null,
                        'deleted_at'               => $parent->is_deleted ? ($parent->deleted_at ?? now()) : null,
                        'deletion_reason'          => $parent->is_deleted
                            ? 'Migrated from ARAMS 1.0 — original reason not recorded'
                            : null,
                        'created_at'               => $parent->submission_date,
                        'updated_at'               => now(),
                        'legacy_id'                => $dataId,
                        'legacy_source'            => 'tbl_research_data',
                    ]);

                    $this->writeSubtype($typeCode, $recordId, $child, $ownerProfileId);

                    $status = match ($parent->status) {
                        'Approved' => 'APPROVED',
                        'Rejected' => 'REJECTED',
                        default    => 'SUBMITTED',
                    };

                    $submissionId = DB::table('submissions')->insertGetId([
                        'research_record_id'       => $recordId,
                        'status'                   => $status,
                        'current_revision'         => 1,
                        'submitted_by'             => $this->idMap['user'][$this->lecturerUserId($parent->lecturer_id)] ?? 1,
                        'faculty_id_at_submission' => $faculties[$legacyFaculties[$parent->faculty_id] ?? ''] ?? null,
                        'first_submitted_at'       => $parent->submission_date,
                        'submitted_at'             => $parent->submission_date,
                        'decided_at'               => $parent->validated_at,
                        'decided_by'               => null,
                        'origin'                   => 'MIGRATED_V1',
                        'created_at'               => $parent->submission_date,
                        'updated_at'               => now(),
                        'legacy_id'                => $dataId,
                        'legacy_source'            => 'tbl_research_data',
                    ]);

                    if ($status !== 'SUBMITTED') {
                        /**
                         * 1.0 could only name an admin as reviewer, and wrote
                         * NULL whenever a TDPP acted — 108 of 272 approvals
                         * have no approver at all. The loss is permanent; it
                         * is recorded as such rather than left blank.
                         */
                        $unknownApprovers += $parent->admin_id ? 0 : 1;

                        DB::table('submission_reviews')->insert([
                            'submission_id'    => $submissionId,
                            'revision_no'      => 1,
                            'reviewer_user_id' => null,
                            'reviewer_role'    => 'ADMIN_LEGACY',
                            'decision'         => $status,
                            'remarks'          => $parent->remarks
                                ?: 'Migrated from ARAMS 1.0 — approver not recorded',
                            'decided_at'       => $parent->validated_at ?? $parent->submission_date,
                            'origin'           => 'MIGRATED_V1',
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }

                    $counts[$typeCode]++;
                }
            }
        }

        $this->report['research'] = [
            'legacy_parents'          => $parents->count(),
            'records_created'         => array_sum($counts),
            'by_type'                 => $counts,
            'bundles_split'           => $bundlesSplit,
            'grant_claims_collapsed'  => count($this->report['collapsed_grant_claims'] ?? []),
            'unknown_effective_dates' => $unknownDates,
            'approvals_without_approver' => $unknownApprovers,
        ];
    }

    private function lecturerUserId(int $lecturerId): ?int
    {
        return $this->legacy('tbl_lecturer')->where('lecturer_id', $lecturerId)->value('user_id');
    }

    /** D4: derive the effective date per type, declaring it when unknown. */
    private function effectiveDate(string $typeCode, object $child): array
    {
        return match ($typeCode) {
            'PUBLICATION' => $child->pub_year
                ? [Carbon::create((int) $child->pub_year, 1, 1)->toDateString(), 'YEAR']
                : [null, 'UNKNOWN'],
            'RESEARCH_INCOME' => $child->year_received
                ? [Carbon::create((int) $child->year_received, 1, 1)->toDateString(), 'YEAR']
                : [null, 'UNKNOWN'],
            // 70 of 71 grants have no start_date; all 18 IP records have
            // neither filing nor grant date. Both land on UNKNOWN.
            'GRANT' => $child->start_date
                ? [Carbon::parse($child->start_date)->toDateString(), 'DAY']
                : [null, 'UNKNOWN'],
            'IP_RECORD' => match (true) {
                (bool) $child->filing_date => [Carbon::parse($child->filing_date)->toDateString(), 'DAY'],
                (bool) $child->grant_date  => [Carbon::parse($child->grant_date)->toDateString(), 'DAY'],
                default                    => [null, 'UNKNOWN'],
            },
            default => [null, 'UNKNOWN'],
        };
    }

    private function title(string $typeCode, object $child): string
    {
        return match ($typeCode) {
            'PUBLICATION'     => $child->title,
            'GRANT'           => $child->grant_title,
            'IP_RECORD'       => $child->ip_title,
            'RESEARCH_INCOME' => $child->source,
            default           => 'Research record',
        };
    }

    private function writeSubtype(string $typeCode, int $recordId, object $child, int $ownerProfileId): void
    {
        match ($typeCode) {
            'PUBLICATION' => $this->writePublication($recordId, $child),
            'GRANT' => DB::table('grants')->insert([
                'research_record_id'     => $recordId,
                'grant_project_id'       => $this->idMap['grant_project'][$child->grant_id],
                'grant_role_id'          => $this->vocab->id('grant_roles', $child->role)
                    ?? DB::table('grant_roles')->where('code', 'MEMBER')->value('id'),
                'allocated_amount'       => $child->amount,
                'owner_staff_profile_id' => $ownerProfileId,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]),
            'IP_RECORD' => DB::table('ip_records')->insert([
                'research_record_id'        => $recordId,
                'ip_type_id'                => $this->vocab->id('ip_types', $child->ip_type)
                    ?? DB::table('ip_types')->where('code', 'PATENT')->value('id'),
                'ip_registration_status_id' => $this->vocab->id('ip_registration_statuses', $child->registration_status),
                'ip_number'                 => $child->ip_number ?: null,
                'filing_date'               => $child->filing_date,
                'grant_date'                => $child->grant_date,
                'raw_inventors'             => $child->inventors,
                'created_at'                => now(),
                'updated_at'                => now(),
            ]),
            'RESEARCH_INCOME' => DB::table('research_incomes')->insert([
                'research_record_id' => $recordId,
                'grant_project_id'   => $child->related_grant_id
                    ? ($this->idMap['grant_project'][$child->related_grant_id] ?? null)
                    : null,
                'income_category_id' => $this->vocab->id('income_categories', $child->income_category)
                    ?? DB::table('income_categories')->where('code', 'RESEARCH_GRANT')->value('id'),
                'source_name'        => $child->source,
                'amount'             => $child->amount,
                'currency'           => 'MYR',
                'year_received'      => $child->year_received,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]),
            default => null,
        };
    }

    private function writePublication(int $recordId, object $child): void
    {
        DB::table('publications')->insert([
            'research_record_id'          => $recordId,
            'journal_name'                => $child->journal_name,
            'issn'                        => $child->issn ?: null,
            'pub_year'                    => $child->pub_year,
            'volume'                      => $child->volume ?: null,
            'issue'                       => $child->issue ?: null,
            'pages'                       => $child->pages ?: null,
            'publication_type_id'         => $this->vocab->id('publication_types', $child->pub_type),
            'author_role_id'              => $this->vocab->id('author_roles', $child->author_role),
            'quartile'                    => $child->quartile ?: 'N/A',
            'impact_factor'               => $child->impact_factor,
            // Duplicate DOIs would violate the new unique index. They are left
            // NULL here and reported, rather than silently dropping a record.
            'doi'                         => $this->uniqueDoi($child->doi),
            'url'                         => $child->url ?: null,
            'student_author'              => (bool) $child->student_author,
            'national_collaboration'      => (bool) $child->national_collaboration,
            'international_collaboration' => (bool) $child->international_collaboration,
            'industries_collaboration'    => (bool) $child->industries_collaboration,
            'raw_authors'                 => $child->authors,
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        // The SET column becomes rows, which is what fixes the 1.0 KPI matcher
        // silently missing every publication indexed 'Scopus,WoS'.
        $indexingIds = DB::table('indexings')->pluck('id', 'code');

        foreach (explode(',', (string) $child->indexing_type) as $token) {
            $id = $this->vocab->id('indexings', $token);

            if ($id) {
                DB::table('publication_indexings')->insertOrIgnore([
                    'research_record_id' => $recordId,
                    'indexing_id'        => $id,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }

        unset($indexingIds);
    }

    /** (project id, owner id) pairs already claimed, to collapse duplicate rows. */
    private array $claimedGrants = [];

    private array $seenDois = [];

    private function uniqueDoi(?string $doi): ?string
    {
        $doi = trim((string) $doi);

        if ($doi === '') {
            return null;
        }

        if (isset($this->seenDois[$doi])) {
            $this->report['duplicate_dois'][] = $doi;

            return null;
        }

        $this->seenDois[$doi] = true;

        return $doi;
    }

    /* ─────────────────────────── H-Index (D2) ─────────────────────────── */

    private function migrateHindex(): void
    {
        $parents = $this->legacy('tbl_research_data')->get()->keyBy('data_id');
        $migrated = 0;
        $conflicts = [];

        foreach ($this->legacy('tbl_hindex')->orderBy('hindex_id')->get() as $row) {
            $parent = $parents[$row->data_id] ?? null;
            $profileId = $parent ? ($this->idMap['lecturer'][$parent->lecturer_id] ?? null) : null;

            if (! $profileId) {
                continue;
            }

            $sourceId = $this->vocab->id('metric_sources', $row->source)
                ?? DB::table('metric_sources')->where('code', 'SCOPUS')->value('id');

            /**
             * The new unique key (staff, source, year) is real, unlike the 1.0
             * constraint on (record_year, data_id) which was unique by
             * construction and enforced nothing. Lecturer 2 has two 2025
             * readings; the second is reported for manual resolution.
             */
            $exists = DB::table('hindex_snapshots')
                ->where('staff_profile_id', $profileId)
                ->where('metric_source_id', $sourceId)
                ->where('record_year', $row->record_year)
                ->exists();

            if ($exists) {
                $conflicts[] = [
                    'staff_profile_id' => $profileId,
                    'year'             => (int) $row->record_year,
                    'legacy_hindex_id' => $row->hindex_id,
                    'value'            => (int) $row->hindex_value,
                ];

                continue;
            }

            DB::table('hindex_snapshots')->insert([
                'staff_profile_id' => $profileId,
                'metric_source_id' => $sourceId,
                'record_year'      => $row->record_year,
                'effective_date'   => Carbon::create((int) $row->record_year, 12, 31)->toDateString(),
                'hindex_value'     => $row->hindex_value,
                'citation_count'   => $row->citation_count,
                'recorded_by'      => null,
                'recorded_at'      => $parent->submission_date ?? now(),
                'source_note'      => 'Migrated from ARAMS 1.0 — outside the validation workflow per D2',
                'created_at'       => now(),
                'updated_at'       => now(),
                'legacy_id'        => $row->hindex_id,
                'legacy_source'    => 'tbl_hindex',
            ]);

            $migrated++;
        }

        $this->report['hindex'] = [
            'migrated'              => $migrated,
            'conflicts_for_review'  => $conflicts,
            'approvals_removed'     => $this->legacy('tbl_hindex')->count(),
        ];
    }

    /* ───────────────────────────── awards ───────────────────────────── */

    private function migrateAwards(): void
    {
        $types = DB::table('research_types')->pluck('id', 'code');
        $faculties = DB::table('faculties')->pluck('id', 'code');
        $legacyFaculties = $this->legacy('tbl_faculty')->pluck('faculty_code', 'faculty_id');
        $lecturerFaculty = $this->legacy('tbl_lecturer')->pluck('faculty_id', 'lecturer_id');

        $migrated = 0;

        foreach ($this->legacy('tbl_award')->orderBy('award_id')->get() as $award) {
            $profileId = $this->idMap['lecturer'][$award->lecturer_id] ?? null;

            if (! $profileId) {
                continue;
            }

            $facultyId = $faculties[$legacyFaculties[$lecturerFaculty[$award->lecturer_id] ?? ''] ?? ''] ?? null;
            $userId = $this->idMap['user'][$this->lecturerUserId($award->lecturer_id)] ?? 1;

            $recordId = DB::table('research_records')->insertGetId([
                'research_type_id'         => $types['AWARD'],
                'owner_staff_profile_id'   => $profileId,
                'display_title'            => $award->award_name,
                'effective_date'           => Carbon::create((int) $award->award_year, 1, 1)->toDateString(),
                'effective_date_precision' => 'YEAR',
                'attributed_faculty_id'    => $facultyId,
                'attributed_at'            => now(),
                'attribution_basis'        => 'EFFECTIVE_DATE',
                'created_at'               => now(),
                'updated_at'               => now(),
                'legacy_id'                => $award->award_id,
                'legacy_source'            => 'tbl_award',
            ]);

            DB::table('awards')->insert([
                'research_record_id' => $recordId,
                'award_level_id'     => $this->vocab->id('award_levels', $award->level),
                'organiser'          => $award->organiser,
                'award_year'         => $award->award_year,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            /**
             * Awards had no workflow in 1.0 yet fed KPI. They enter as
             * pre-approved with no reviewer, so their unvalidated provenance
             * stays visible rather than being laundered into a clean approval.
             */
            $submissionId = DB::table('submissions')->insertGetId([
                'research_record_id'       => $recordId,
                'status'                   => 'APPROVED',
                'current_revision'         => 1,
                'submitted_by'             => $userId,
                'faculty_id_at_submission' => $facultyId,
                'first_submitted_at'       => now(),
                'submitted_at'             => now(),
                'decided_at'               => now(),
                'decided_by'               => null,
                'origin'                   => 'MIGRATED_V1',
                'created_at'               => now(),
                'updated_at'               => now(),
                'legacy_id'                => $award->award_id,
                'legacy_source'            => 'tbl_award',
            ]);

            DB::table('submission_reviews')->insert([
                'submission_id'    => $submissionId,
                'revision_no'      => 1,
                'reviewer_user_id' => null,
                'reviewer_role'    => 'ADMIN_LEGACY',
                'decision'         => 'APPROVED',
                'remarks'          => 'Migrated from ARAMS 1.0 — awards had no validation workflow',
                'decided_at'       => now(),
                'origin'           => 'MIGRATED_V1',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $migrated++;
        }

        $this->report['awards'] = ['migrated' => $migrated, 'entered_workflow_unvalidated' => $migrated];
    }

    /* ────────────────────────────── KPI ────────────────────────────── */

    private function migrateKpi(): void
    {
        $periods  = DB::table('kpi_periods')->pluck('id', 'code');
        $measures = DB::table('kpi_measures')->pluck('id', 'code');
        $faculties = DB::table('faculties')->pluck('id', 'code');
        $legacyFaculties = $this->legacy('tbl_faculty')->pluck('faculty_code', 'faculty_id');

        $metricMap = [
            'Publications'         => 'PUBLICATION_COUNT',
            'Q1 Publications'      => 'PUBLICATION_COUNT',
            'Q2 Publications'      => 'PUBLICATION_COUNT',
            'Grants'               => 'GRANT_COUNT',
            'H-Index'              => 'HINDEX_AVERAGE',
            'Research Income (RM)' => 'INCOME_TOTAL',
            'IP Records'           => 'IP_COUNT',
        ];

        $targets = 0;
        $skipped = [];

        foreach ($this->legacy('tbl_kpi_target')->get() as $row) {
            $measureCode = $metricMap[$row->metric] ?? null;
            $periodId = $periods[(string) $row->year] ?? null;

            if (! $measureCode || ! $periodId) {
                $skipped[] = ['metric' => $row->metric, 'year' => $row->year, 'reason' => 'no matching measure or period'];

                continue;
            }

            $facultyId = $row->faculty_id
                ? ($faculties[$legacyFaculties[$row->faculty_id] ?? ''] ?? null)
                : null;

            // 'Q1 Publications' and 'Publications' are one measure with
            // different criteria; the variant is what keeps them distinct.
            $variant = str_starts_with($row->metric, 'Q') ? substr($row->metric, 0, 2) : null;

            $targetId = DB::table('kpi_targets')->insertGetId([
                'kpi_period_id'  => $periodId,
                'kpi_measure_id' => $measures[$measureCode],
                'scope_type'     => $facultyId ? 'FACULTY' : 'INSTITUTION',
                'scope_id'       => $facultyId,
                'variant_code'   => $variant,
                'target_value'   => $row->target_value,
                'description'    => $row->description,
                'created_at'     => now(),
                'updated_at'     => now(),
                'legacy_id'      => $row->kpi_id,
                'legacy_source'  => 'tbl_kpi_target',
            ]);

            // Quartile-specific 1.0 metrics become a criterion row rather than
            // a separate measure, which is what makes the model extensible.
            if ($variant !== null) {
                DB::table('kpi_target_criteria')->insert([
                    'kpi_target_id' => $targetId,
                    'field_path'    => 'quartile',
                    'operator'      => '=',
                    'value'         => $variant,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            $targets++;
        }

        $assignments = 0;

        foreach ($this->legacy('tbl_kpi_task')->get() as $task) {
            $profileId = $this->idMap['lecturer'][$task->lecturer_id] ?? null;
            $assignerId = $this->idMap['tdpp'][$task->tdpp_id] ?? null;

            if (! $profileId) {
                continue;
            }

            $measureCode = match ($task->task_type) {
                'Publication' => 'PUBLICATION_COUNT',
                'Grant' => 'GRANT_COUNT',
                'IP' => 'IP_COUNT',
                'Research Income' => 'INCOME_TOTAL',
                'Award' => 'AWARD_COUNT',
                'H-Index' => 'HINDEX_MAX',
                default => null,
            };

            if (! $measureCode) {
                continue;
            }

            // 1.0 tasks carried a deadline but no period, which is why the
            // matcher counted a lecturer's whole career. The deadline year is
            // the closest honest reading of intent.
            $periodCode = (string) Carbon::parse($task->deadline)->year;
            $periodId = $periods[$periodCode] ?? null;

            if (! $periodId) {
                $skipped[] = ['task' => $task->task_id, 'reason' => "no period for {$periodCode}"];

                continue;
            }

            $targetId = DB::table('kpi_targets')->insertGetId([
                'kpi_period_id'  => $periodId,
                'kpi_measure_id' => $measures[$measureCode],
                'scope_type'     => 'STAFF',
                'scope_id'       => $profileId,
                // Two 1.0 tasks can share a period, measure and lecturer while
                // differing in criteria or intent; the task id keeps them apart.
                'variant_code'   => 'TASK-' . $task->task_id,
                'target_value'   => max(1, (int) $task->target_count),
                'description'    => $task->task_title,
                'created_at'     => now(),
                'updated_at'     => now(),
                'legacy_id'      => $task->task_id,
                'legacy_source'  => 'tbl_kpi_task',
            ]);

            foreach ([
                'quartile' => $task->criteria_quartile,
                'indexings' => $task->criteria_indexing,
            ] as $field => $value) {
                if ($value && $value !== 'Any') {
                    DB::table('kpi_target_criteria')->insert([
                        'kpi_target_id' => $targetId,
                        'field_path'    => $field,
                        // Set-valued facts use `contains`, never `=` — the 1.0
                        // matcher used equality and missed multi-indexed work.
                        'operator'      => $field === 'indexings' ? 'contains' : '=',
                        'value'         => $field === 'indexings'
                            ? strtoupper($value === 'WOS' ? 'WOS' : $value)
                            : $value,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }

            DB::table('kpi_assignments')->insert([
                'kpi_target_id'                => $targetId,
                'staff_profile_id'             => $profileId,
                'assigned_by_staff_profile_id' => $assignerId,
                'assigned_at'                  => $task->assigned_date,
                'deadline'                     => $task->deadline,
                // Progress is deliberately NOT carried over: 1.0 computed it by
                // counting a lecturer's entire history, which is the defect D4
                // replaces. It is recomputed from contributions instead.
                'status'                       => 'OPEN',
                'note'                         => $task->task_desc,
                'created_at'                   => now(),
                'updated_at'                   => now(),
                'legacy_id'                    => $task->task_id,
                'legacy_source'                => 'tbl_kpi_task',
            ]);

            $assignments++;
        }

        $this->report['kpi'] = [
            'targets_created'     => $targets,
            'assignments_created' => $assignments,
            'progress_reset'      => $assignments,
            'skipped'             => $skipped,
        ];
    }

    /* ───────────────────────────── audit ───────────────────────────── */

    private function migrateAudit(): void
    {
        $migrated = 0;

        foreach ($this->legacy('tbl_audit_log')->orderBy('log_id')->get() as $row) {
            DB::table('audit_events')->insert([
                'actor_user_id'  => $this->idMap['user'][$row->user_id] ?? null,
                'actor_role'     => null,
                // 1.0 stored free text, including the typo 'Rejectd Submission'.
                // It is preserved verbatim under a legacy action code rather
                // than being guessed into the new enum.
                'action'         => 'legacy.' . str($row->action)->slug('_'),
                'auditable_type' => $row->target_type,
                'auditable_id'   => $row->target_id,
                'context'        => json_encode([
                    'legacy_action' => $row->action,
                    'details'       => $row->details,
                ]),
                'created_at'     => $row->logged_at,
                'legacy_id'      => $row->log_id,
                'legacy_source'  => 'tbl_audit_log',
            ]);

            $migrated++;
        }

        $this->report['audit'] = ['migrated' => $migrated];
    }

    /* ───────────────────────── reconciliation ───────────────────────── */

    private function reconcile(): void
    {
        $legacyApproved = $this->legacy('tbl_research_data')
            ->where('status', 'Approved')->where('is_deleted', 0)->count();

        $newApproved = DB::table('submissions')
            ->where('status', 'APPROVED')
            ->whereIn('research_record_id', DB::table('research_records')->whereNull('deleted_at')->select('id'))
            ->count();

        $byFaculty = DB::table('research_records')
            ->join('faculties', 'faculties.id', '=', 'research_records.attributed_faculty_id')
            ->whereNull('research_records.deleted_at')
            ->groupBy('faculties.code')
            ->pluck(DB::raw('COUNT(*)'), 'faculties.code')
            ->all();

        $this->report['reconciliation'] = [
            'legacy_approved_parents'   => $legacyApproved,
            'new_approved_submissions'  => $newApproved,
            // Approved parents that held several records become several
            // approved submissions, so a rise here is expected and explained.
            'difference_explained_by_split' => $newApproved - $legacyApproved,
            'records_by_attributed_faculty' => $byFaculty,
            'unmapped_vocabulary'       => $this->vocab->unmapped(),
            'totals' => [
                'users'            => DB::table('users')->count(),
                'staff_profiles'   => DB::table('staff_profiles')->count(),
                'research_records' => DB::table('research_records')->count(),
                'submissions'      => DB::table('submissions')->count(),
                'reviews'          => DB::table('submission_reviews')->count(),
                'hindex_snapshots' => DB::table('hindex_snapshots')->count(),
                'grant_projects'   => DB::table('grant_projects')->count(),
                'kpi_targets'      => DB::table('kpi_targets')->count(),
                'audit_events'     => DB::table('audit_events')->count(),
            ],
        ];
    }
}
