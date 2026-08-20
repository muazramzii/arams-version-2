<?php

namespace App\Policies;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;

/**
 * D1 lives here.
 *
 * TDPP is the only role that may review, and only for a faculty where they
 * hold a current appointment. There is no Admin branch in review(), approve(),
 * reject() or requestRevision() — that absence is the decision, not an
 * oversight.
 *
 * ARAMS 1.0's central defect was that authorization lived in page visibility:
 * 24 of 25 portal pages performed no role check at all, so any authenticated
 * user could open another faculty's validation queue by typing its URL.
 */
class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Submission $submission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // The owner always sees their own.
        if ($submission->submitted_by === $user->id) {
            return true;
        }

        // A TDPP sees their own faculty's queue and nothing beyond it.
        return $user->canValidateForFaculty((int) $submission->faculty_id_at_submission);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->staffProfile !== null;
    }

    /** Owner may edit only while the record is theirs to change. */
    public function update(User $user, Submission $submission): bool
    {
        return $user->is_active
            && $submission->submitted_by === $user->id
            && $submission->isEditableByOwner();
    }

    public function submit(User $user, Submission $submission): bool
    {
        return $user->is_active
            && $submission->submitted_by === $user->id
            && in_array($submission->status, [
                SubmissionStatus::Draft,
                SubmissionStatus::RevisionRequested,
            ], true);
    }

    public function withdraw(User $user, Submission $submission): bool
    {
        return $user->is_active
            && $submission->submitted_by === $user->id
            && ! $submission->isTerminal()
            && $submission->status !== SubmissionStatus::Approved;
    }

    /**
     * The core D1 rule. Note the two conditions beyond role: a current
     * appointment for *this* submission's faculty, and never one's own work.
     */
    public function review(User $user, Submission $submission): bool
    {
        if (! $user->is_active || ! $user->isTdpp()) {
            return false;
        }

        if ($submission->submitted_by === $user->id) {
            return false;
        }

        return $user->canValidateForFaculty((int) $submission->faculty_id_at_submission);
    }

    public function claim(User $user, Submission $submission): bool
    {
        return $this->review($user, $submission)
            && $submission->status === SubmissionStatus::Submitted;
    }

    public function decide(User $user, Submission $submission): bool
    {
        return $this->review($user, $submission)
            && $submission->status === SubmissionStatus::UnderReview;
    }

    /**
     * Superseding an approved record is a data correction, not a validation
     * decision — the one workflow action reserved to Admin.
     */
    public function supersede(User $user, Submission $submission): bool
    {
        return $user->is_active
            && $user->isAdmin()
            && $submission->status === SubmissionStatus::Approved;
    }

    public function delete(User $user, Submission $submission): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
