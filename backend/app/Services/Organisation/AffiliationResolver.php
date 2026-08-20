<?php

namespace App\Services\Organisation;

use App\Models\Faculty;
use App\Models\StaffProfile;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Resolves which faculty a person belonged to at a point in time, and which
 * faculty a research record should be credited to.
 *
 * ARAMS 1.0 had no such concept: analytics attributed everything through the
 * lecturer's *current* faculty, so when lecturer 1 transferred FSKTM -> FKAAB,
 * 37 records dating back to 2016 moved with her and both faculties' published
 * history silently changed.
 */
class AffiliationResolver
{
    /** The faculty in force for this person on the given date. */
    public function facultyOn(StaffProfile $staff, ?DateTimeInterface $date): ?int
    {
        return $staff->affiliationOn($date)?->faculty_id;
    }

    /**
     * Attribution for a research record, with an explicit basis so the
     * approximation is visible rather than assumed.
     *
     * Records whose effective date is UNKNOWN — 88 of them migrate that way,
     * being 70 grants and all 18 IP records — fall back to the submission
     * date and are marked as such.
     *
     * @return array{faculty_id: ?int, basis: string}
     */
    public function attributionFor(
        StaffProfile $staff,
        ?DateTimeInterface $effectiveDate,
        ?DateTimeInterface $submittedAt = null,
    ): array {
        if ($effectiveDate !== null) {
            $facultyId = $this->facultyOn($staff, $effectiveDate);

            if ($facultyId !== null) {
                return ['faculty_id' => $facultyId, 'basis' => 'EFFECTIVE_DATE'];
            }
        }

        return [
            'faculty_id' => $this->facultyOn($staff, $submittedAt ?? now()),
            'basis'      => 'SUBMISSION_DATE_FALLBACK',
        ];
    }

    /**
     * Faculties with no current TDPP appointment.
     *
     * Under D1 the TDPP is the only validator and there is no Admin fallback,
     * so a faculty without one cannot process submissions at all. FKAAS has
     * 77 lecturers and no appointment, which is why this must be a first-class
     * query rather than something discovered when a queue stays empty.
     *
     * @return Collection<int, Faculty>
     */
    public function facultiesWithoutValidator(): Collection
    {
        return Faculty::query()
            ->where('is_active', true)
            ->whereDoesntHave('leaders', fn ($q) => $q->whereNull('valid_to'))
            ->orderBy('code')
            ->get();
    }

    /** User ids of everyone who may validate for this faculty right now. */
    public function validatorUserIdsFor(int $facultyId): Collection
    {
        return Faculty::find($facultyId)?->currentLeaders()
            ->with('staffProfile:id,user_id')
            ->get()
            ->pluck('staffProfile.user_id')
            ->filter()
            ->values() ?? collect();
    }
}
