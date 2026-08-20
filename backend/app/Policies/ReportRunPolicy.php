<?php

namespace App\Policies;

use App\Models\{ReportRun};
use App\Models\User;

class ReportRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /** A generated artifact belongs to whoever asked for it. */
    public function view(User $user, ReportRun $run): bool
    {
        return $user->is_active && ($user->isAdmin() || $run->requested_by === $user->id);
    }

    public function download(User $user, ReportRun $run): bool
    {
        return $this->view($user, $run);
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }
}
