<?php

namespace App\Policies;

use App\Models\ResearchRecord;
use App\Models\User;

/**
 * A lecturer owns their own research and nothing else.
 *
 * ARAMS 1.0's pages/admin/lecturer_detail.php read $_GET['id'] with no role or
 * ownership check whatsoever, so any authenticated lecturer could read a
 * colleague's full profile, email address and complete research history by
 * changing a number in the URL.
 */
class ResearchRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ResearchRecord $record): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($this->owns($user, $record)) {
            return true;
        }

        // A TDPP sees records attributed to a faculty they currently serve,
        // and those still sitting in their queue.
        if ($user->isTdpp()) {
            if ($record->attributed_faculty_id
                && $user->canValidateForFaculty((int) $record->attributed_faculty_id)) {
                return true;
            }

            $queueFaculty = $record->submission?->faculty_id_at_submission;

            return $queueFaculty !== null && $user->canValidateForFaculty((int) $queueFaculty);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->staffProfile !== null;
    }

    /** Editable by its owner only while the submission allows it. */
    public function update(User $user, ResearchRecord $record): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (! $this->owns($user, $record)) {
            return false;
        }

        return $record->submission === null || $record->submission->isEditableByOwner();
    }

    /**
     * A lecturer may delete only an unsubmitted draft. Anything that has been
     * through validation is institutional record, and removing it is an
     * audited Admin action.
     */
    public function delete(User $user, ResearchRecord $record): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $this->owns($user, $record)
            && $record->submission?->status->value === 'DRAFT';
    }

    public function restore(User $user, ResearchRecord $record): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    private function owns(User $user, ResearchRecord $record): bool
    {
        return $user->staffProfile !== null
            && $record->owner_staff_profile_id === $user->staffProfile->id;
    }
}
