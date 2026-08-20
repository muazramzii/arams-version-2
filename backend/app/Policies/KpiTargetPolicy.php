<?php

namespace App\Policies;

use App\Models\{KpiTarget};
use App\Models\User;

class KpiTargetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, KpiTarget $target): bool
    {
        if (! $user->is_active) { return false; }
        if ($user->isAdmin()) { return true; }

        return match ($target->scope_type->value) {
            'INSTITUTION' => true,
            'FACULTY'     => $user->canValidateForFaculty((int) $target->scope_id),
            'STAFF'       => $target->scope_id === $user->staffProfile?->id
                             || $this->tdppOversees($user, (int) $target->scope_id),
            default       => false,
        };
    }

    /** Institution-wide targets are Admin's; faculty targets are the TDPP's. */
    public function create(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isTdpp());
    }

    public function update(User $user, KpiTarget $target): bool
    {
        if (! $user->is_active) { return false; }
        if ($user->isAdmin()) { return true; }
        if (! $user->isTdpp()) { return false; }

        return match ($target->scope_type->value) {
            'FACULTY' => $user->canValidateForFaculty((int) $target->scope_id),
            'STAFF'   => $this->tdppOversees($user, (int) $target->scope_id),
            default   => false,   // INSTITUTION is Admin-only
        };
    }

    public function delete(User $user, KpiTarget $target): bool
    {
        return $this->update($user, $target);
    }

    private function tdppOversees(User $user, int $staffProfileId): bool
    {
        $faculty = \App\Models\StaffProfile::find($staffProfileId)?->currentFacultyId();

        return $faculty !== null && $user->canValidateForFaculty($faculty);
    }
}
