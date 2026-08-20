<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Single entry point for the audit trail.
 *
 * ARAMS 1.0 wrote audit rows from whichever controller remembered to, using
 * free-text action strings — hence the typo 'Rejectd Submission', and hence
 * 7 audit rows covering Research_Data against 272 approvals. Emitting from
 * the service layer instead means coverage follows significance.
 */
class AuditLogger
{
    // Workflow
    public const SUBMITTED           = 'submission.submitted';
    public const WITHDRAWN           = 'submission.withdrawn';
    public const CLAIMED             = 'submission.claimed';
    public const APPROVED            = 'submission.approved';
    public const REJECTED            = 'submission.rejected';
    public const REVISION_REQUESTED  = 'submission.revision_requested';
    public const RESUBMITTED         = 'submission.resubmitted';
    public const SUPERSEDED          = 'submission.superseded';

    // Data correction
    public const RECORD_CREATED      = 'research_record.created';
    public const RECORD_UPDATED      = 'research_record.updated';
    public const RECORD_DELETED      = 'research_record.deleted';
    public const RECORD_RESTORED     = 'research_record.restored';

    // Identity and organisation
    public const LOGIN               = 'auth.login';
    public const LOGIN_FAILED        = 'auth.login_failed';
    public const LOGOUT              = 'auth.logout';
    public const PASSWORD_CHANGED    = 'auth.password_changed';
    public const PASSWORD_RESET      = 'auth.password_reset';
    public const USER_ACTIVATED      = 'user.activated';
    public const USER_DEACTIVATED    = 'user.deactivated';
    public const FACULTY_TRANSFER    = 'staff.faculty_transferred';
    public const APPOINTMENT_CREATED = 'faculty_leader.appointed';
    public const APPOINTMENT_ENDED   = 'faculty_leader.ended';

    // KPI and reporting
    public const KPI_TARGET_SET      = 'kpi.target_set';
    public const KPI_ASSIGNED        = 'kpi.assigned';
    public const KPI_UNASSIGNED      = 'kpi.unassigned';
    public const REPORT_GENERATED    = 'report.generated';
    public const REPORT_DOWNLOADED   = 'report.downloaded';

    public function log(
        string $action,
        ?Model $subject = null,
        ?array $changes = null,
        ?array $context = null,
    ): AuditEvent {
        $user = Auth::user();

        return AuditEvent::create([
            'actor_user_id'  => $user?->id,
            'actor_role'     => $user?->role?->value,
            'action'         => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id'   => $subject?->getKey(),
            'changes'        => $changes,
            'context'        => $context,
            'ip_address'     => Request::ip(),
            'user_agent'     => substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /**
     * Log an update with before/after values — the thing 1.0 never captured,
     * which is why an Admin edit to an approved record left no trace of what
     * had actually changed.
     */
    public function logChange(string $action, Model $subject, array $before, array $after): AuditEvent
    {
        $diff = [];

        foreach ($after as $key => $newValue) {
            $oldValue = $before[$key] ?? null;

            if ($oldValue != $newValue) {
                $diff[$key] = ['from' => $oldValue, 'to' => $newValue];
            }
        }

        return $this->log($action, $subject, $diff ?: null);
    }
}
