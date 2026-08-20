<?php

namespace App\Services\Notification;

use App\Models\Submission;
use App\Models\User;
use App\Services\Organisation\AffiliationResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Typed notifications.
 *
 * ARAMS 1.0 built each message with CONCAT inside whichever controller fired
 * it, storing a finished English sentence with no type, no actor and no link.
 * Here the row stores a type plus structured data, and the message is rendered
 * at display time — which is what makes it translatable, filterable, groupable
 * and linkable.
 *
 * The routing bug is fixed too: 1.0 told every Admin about a new submission
 * and never told the TDPP, so the only role permitted to act was the only role
 * not informed.
 */
class NotificationService
{
    public const SUBMISSION_RECEIVED   = 'submission.received';
    public const SUBMISSION_APPROVED   = 'submission.approved';
    public const SUBMISSION_REJECTED   = 'submission.rejected';
    public const REVISION_REQUESTED    = 'submission.revision_requested';
    public const KPI_ASSIGNED          = 'kpi.assigned';
    public const KPI_MILESTONE         = 'kpi.milestone';
    public const KPI_DEADLINE_NEAR     = 'kpi.deadline_near';
    public const NO_VALIDATOR          = 'faculty.no_validator';
    public const ACCOUNT_CHANGED       = 'account.changed';

    public function __construct(private readonly AffiliationResolver $affiliations) {}

    /** Tell the serving TDPPs that something is waiting for them. */
    public function submissionReceived(Submission $submission): void
    {
        $recipients = $this->affiliations
            ->validatorUserIdsFor((int) $submission->faculty_id_at_submission);

        $this->send($recipients, self::SUBMISSION_RECEIVED, [
            'submission_id' => $submission->id,
            'title'         => $submission->researchRecord?->display_title,
            'type'          => $submission->researchRecord?->researchType?->code,
            'author'        => $submission->researchRecord?->owner?->full_name,
            'revision'      => $submission->current_revision,
        ], "/submissions/{$submission->id}");
    }

    public function submissionDecided(Submission $submission, string $decision, ?string $remarks): void
    {
        $type = match ($decision) {
            'APPROVED'           => self::SUBMISSION_APPROVED,
            'REJECTED'           => self::SUBMISSION_REJECTED,
            'REVISION_REQUESTED' => self::REVISION_REQUESTED,
            default              => self::SUBMISSION_APPROVED,
        };

        $this->send(collect([$submission->submitted_by]), $type, [
            'submission_id' => $submission->id,
            'title'         => $submission->researchRecord?->display_title,
            'decision'      => $decision,
            'remarks'       => $remarks,
            // The lecturer can act on this one; the UI should say so.
            'actionable'    => $decision === 'REVISION_REQUESTED',
        ], "/submissions/{$submission->id}");
    }

    /**
     * Raised to Admin when a faculty has no serving TDPP — a coverage alert,
     * not a validation request. Under D1 nobody else can step in.
     */
    public function noValidator(int $facultyId, string $facultyCode): void
    {
        $admins = User::query()->where('role', 'Admin')->where('is_active', true)->pluck('id');

        $this->send($admins, self::NO_VALIDATOR, [
            'faculty_id'   => $facultyId,
            'faculty_code' => $facultyCode,
            'severity'     => 'warning',
        ], '/admin/faculties');
    }

    public function kpiAssigned(int $recipientUserId, array $payload): void
    {
        $this->send(collect([$recipientUserId]), self::KPI_ASSIGNED, $payload, '/kpi/assignments');
    }

    public function kpiMilestone(int $recipientUserId, array $payload): void
    {
        $this->send(collect([$recipientUserId]), self::KPI_MILESTONE, $payload, '/kpi/assignments');
    }

    /**
     * @param Collection<int, int> $userIds
     */
    private function send(Collection $userIds, string $type, array $data, ?string $actionUrl = null): void
    {
        $userIds = $userIds->filter()->unique();

        if ($userIds->isEmpty()) {
            return;
        }

        $optedOut = DB::table('notification_preferences')
            ->whereIn('user_id', $userIds)
            ->where('type', $type)
            ->where('in_app', false)
            ->pluck('user_id')
            ->all();

        $rows = $userIds
            ->reject(fn ($id) => in_array($id, $optedOut, true))
            ->map(fn ($id) => [
                'id'                 => (string) Str::uuid(),
                'type'               => $type,
                'notifiable_user_id' => $id,
                'data'               => json_encode($data),
                'action_url'         => $actionUrl,
                'read_at'            => null,
                'created_at'         => now(),
                'updated_at'         => now(),
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('notifications')->insert($rows);
        }
    }
}
