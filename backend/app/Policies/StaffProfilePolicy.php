<?php

namespace App\Policies;

use App\Models\{StaffProfile};
use App\Models\User;

class StaffProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, StaffProfile $profile): bool
    {
        if (! $user->is_active) { return false; }
        if ($user->isAdmin() || $profile->user_id === $user->id) { return true; }

        // A TDPP sees staff currently affiliated to a faculty they serve.
        $facultyId = $profile->currentFacultyId();

        return $facultyId !== null && $user->canValidateForFaculty($facultyId);
    }

    /** Everyone maintains their own profile; only Admin edits anyone else's. */
    public function update(User $user, StaffProfile $profile): bool
    {
        return $user->is_active && ($profile->user_id === $user->id || $user->isAdmin());
    }

    public function transferFaculty(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
