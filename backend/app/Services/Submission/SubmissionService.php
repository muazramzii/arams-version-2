<?php

namespace App\Services\Submission;

use App\Enums\SubmissionStatus;
use App\Models\ResearchRecord;
use App\Models\Submission;
use App\Models\SubmissionReview;
use App\Models\SubmissionRevision;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Kpi\KpiProgressCalculator;
use App\Services\Notification\NotificationService;
use App\Services\Organisation\AffiliationResolver;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The submission lifecycle, in one place.
 *
 * ARAMS 1.0 spread this across api/submit_research.php and api/validate.php,
 * each doing its own thing: validate.php overwrote status, remarks and
 * validated_at in a single UPDATE with no history, wrote NULL into the only
 * reviewer column whenever a TDPP acted, and notified Admins who were not
 * permitted to act on what they were being told about.
 */
class SubmissionService
{
    public function __construct(
        private readonly SubmissionStateMachine $stateMachine,
        private readonly AffiliationResolver $affiliations,
        private readonly AuditLogger $audit,
        private readonly KpiProgressCalculator $kpi,
        private readonly NotificationService $notifications,
    ) {}

    /** Create the workflow row for a newly created research record. */
    public function createDraft(ResearchRecord $record, User $owner): Submission
    {
        return Submission::create([
            'research_record_id'       => $record->id,
            'status'                   => SubmissionStatus::Draft,
            'current_revision'         => 1,
            'submitted_by'             => $owner->id,
            'faculty_id_at_submission' => $owner->staffProfile?->currentFacultyId(),
            'origin'                   => 'ARAMS_2',
        ]);
    }

    /**
     * Send for validation, or resubmit after a revision request.
     *
     * Refuses when the submitter's faculty has no serving TDPP. Under D1 there
     * is no Admin fallback, so accepting the record would park it in a queue
     * nobody can act on — FKAAS currently has 77 lecturers and no appointment.
     */
    public function submit(Submission $submission, User $actor): Submission
    {
        $this->stateMachine->assertTransition($actor, $submission, SubmissionStatus::Submitted);

        $facultyId = $actor->staffProfile?->currentFacultyId();

        if ($facultyId === null) {
            throw new RuntimeException(
                'Your staff record has no faculty affiliation. Contact the administrator.'
            );
        }

        if ($this->affiliations->validatorUserIdsFor($facultyId)->isEmpty()) {
            throw new RuntimeException(
                'Your faculty currently has no TDPP appointed, so submissions cannot be '
                . 'validated. The administrator has been notified.'
            );
        }

        $isResubmission = $submission->status === SubmissionStatus::RevisionRequested;

        return DB::transaction(function () use ($submission, $actor, $facultyId, $isResubmission) {
            if ($isResubmission) {
                $submission->current_revision++;
            }

            $submission->status                   = SubmissionStatus::Submitted;
            $submission->submitted_at             = now();
            $submission->first_submitted_at    ??= now();
            $submission->faculty_id_at_submission = $facultyId;
            $submission->claimed_by               = null;
            $submission->claimed_at               = null;
            $submission->save();

            // Snapshot what the reviewer will actually see at this revision.
            SubmissionRevision::create([
                'submission_id' => $submission->id,
                'revision_no'   => $submission->current_revision,
                'payload'       => $this->snapshot($submission->researchRecord),
                'submitted_by'  => $actor->id,
                'submitted_at'  => now(),
            ]);

            $this->audit->log(
                $isResubmission ? AuditLogger::RESUBMITTED : AuditLogger::SUBMITTED,
                $submission,
                null,
                ['revision' => $submission->current_revision, 'faculty_id' => $facultyId],
            );

            // Goes to the faculty's serving TDPPs — the role that can actually
            // act on it. ARAMS 1.0 notified every Admin and no TDPP, so the
            // only role permitted to validate was the only one not told.
            $this->notifications->submissionReceived(
                $submission->load(['researchRecord.researchType', 'researchRecord.owner'])
            );

            return $submission->refresh();
        });
    }

    public function withdraw(Submission $submission, User $actor): Submission
    {
        $this->stateMachine->assertTransition($actor, $submission, SubmissionStatus::Withdrawn);

        $submission->update(['status' => SubmissionStatus::Withdrawn]);
        $this->audit->log(AuditLogger::WITHDRAWN, $submission);

        return $submission;
    }

    /** Claim for review, so two TDPPs in one faculty do not duplicate work. */
    public function claim(Submission $submission, User $actor): Submission
    {
        $this->stateMachine->assertTransition($actor, $submission, SubmissionStatus::UnderReview);

        $submission->update([
            'status'     => SubmissionStatus::UnderReview,
            'claimed_by' => $actor->id,
            'claimed_at' => now(),
        ]);

        $this->audit->log(AuditLogger::CLAIMED, $submission);

        return $submission;
    }

    /**
     * Record a decision.
     *
     * decided_by references users, so a TDPP approval actually records its
     * approver — 108 of 272 approvals in the 1.0 data have none, because the
     * only reviewer column pointed at tbl_admin.
     */
    public function decide(
        Submission $submission,
        User $actor,
        SubmissionStatus $decision,
        ?string $remarks = null,
    ): Submission {
        $this->stateMachine->assertTransition($actor, $submission, $decision, $remarks);

        return DB::transaction(function () use ($submission, $actor, $decision, $remarks) {
            $submission->status     = $decision;
            $submission->decided_by = $actor->id;
            $submission->decided_at = now();
            $submission->save();

            SubmissionReview::create([
                'submission_id'    => $submission->id,
                'revision_no'      => $submission->current_revision,
                'reviewer_user_id' => $actor->id,
                'reviewer_role'    => 'TDPP',
                'decision'         => $decision === SubmissionStatus::Approved
                    ? 'APPROVED'
                    : ($decision === SubmissionStatus::Rejected ? 'REJECTED' : 'REVISION_REQUESTED'),
                'remarks'          => $remarks,
                'decided_at'       => now(),
                'origin'           => 'ARAMS_2',
            ]);

            if ($decision === SubmissionStatus::Approved) {
                $this->freezeAttribution($submission);
                // Only approved records contribute — one definition, one place.
                $this->kpi->recomputeForRecord($submission->researchRecord);
            }

            $this->notifications->submissionDecided(
                $submission->load('researchRecord'),
                $decision->value,
                $remarks,
            );

            $this->audit->log(match ($decision) {
                SubmissionStatus::Approved          => AuditLogger::APPROVED,
                SubmissionStatus::Rejected          => AuditLogger::REJECTED,
                SubmissionStatus::RevisionRequested => AuditLogger::REVISION_REQUESTED,
                default                             => AuditLogger::APPROVED,
            }, $submission, null, ['revision' => $submission->current_revision, 'remarks' => $remarks]);

            return $submission->refresh();
        });
    }

    /**
     * Resolve the crediting faculty once, at approval, and freeze it.
     *
     * A later transfer must not move historical output between faculties —
     * which is exactly what happened in 1.0, where 37 of one lecturer's
     * records silently moved from FSKTM to FKAAB.
     */
    private function freezeAttribution(Submission $submission): void
    {
        $record = $submission->researchRecord;
        $owner  = $record->owner;

        if ($owner === null) {
            return;
        }

        $attribution = $this->affiliations->attributionFor(
            $owner,
            $record->effective_date,
            $submission->first_submitted_at,
        );

        $record->update([
            'attributed_faculty_id' => $attribution['faculty_id'],
            'attributed_at'         => now(),
            'attribution_basis'     => $attribution['basis'],
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(ResearchRecord $record): array
    {
        return [
            'record' => $record->only([
                'research_type_id', 'display_title', 'effective_date',
                'effective_date_precision',
            ]),
            'detail' => $record->detail()?->toArray(),
        ];
    }
}
