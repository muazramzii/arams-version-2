<?php

namespace App\Policies;

use App\Models\{HindexSnapshot};
use App\Models\User;

class HindexSnapshotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, HindexSnapshot $snapshot): bool
    {
        if (! $user->is_active) { return false; }
        if ($user->isAdmin() || $snapshot->staff_profile_id === $user->staffProfile?->id) { return true; }

        $facultyId = $snapshot->staffProfile?->currentFacultyId();

        return $facultyId !== null && $user->canValidateForFaculty($facultyId);
    }

    /**
     * D2: H-Index is an institution-maintained metric, not submitted research.
     * Recording one is an Admin data-load action, and it never enters the
     * validation workflow.
     */
    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, HindexSnapshot $snapshot): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function delete(User $user, HindexSnapshot $snapshot): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
