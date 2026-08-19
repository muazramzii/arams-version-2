<?php

namespace App\Models;

use App\Enums\RecordOrigin;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Workflow state for exactly one research record (D3, enforced by the UNIQUE
 * index on research_record_id).
 *
 * decided_by points at `users`, not at an admin table. That single change fixes
 * the 1.0 defect where the only reviewer column was tbl_research_data.admin_id
 * with an FK to tbl_admin: a TDPP has no row there, so every TDPP approval
 * wrote NULL and 108 of 272 approved records have no recorded approver.
 */
class Submission extends Model
{
    protected $fillable = [
        'research_record_id', 'status', 'current_revision', 'submitted_by',
        'faculty_id_at_submission', 'first_submitted_at', 'submitted_at',
        'claimed_by', 'claimed_at', 'decided_by', 'decided_at', 'origin',
    ];

    protected function casts(): array
    {
        return [
            'status'             => SubmissionStatus::class,
            'origin'             => RecordOrigin::class,
            'current_revision'   => 'integer',
            'first_submitted_at' => 'datetime',
            'submitted_at'       => 'datetime',
            'claimed_at'         => 'datetime',
            'decided_at'         => 'datetime',
        ];
    }

    public function researchRecord(): BelongsTo
    {
        return $this->belongsTo(ResearchRecord::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function facultyAtSubmission(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'faculty_id_at_submission');
    }

    /** Append-only decision history. Never updated, never deleted. */
    public function reviews(): HasMany
    {
        return $this->hasMany(SubmissionReview::class)->orderBy('decided_at');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SubmissionRevision::class)->orderBy('revision_no');
    }

    public function latestReview(): ?SubmissionReview
    {
        return $this->reviews()->latest('decided_at')->first();
    }

    /**
     * Migrated 1.0 approvals whose approver was never recorded. The loss is
     * permanent; the UI must show it as such rather than rendering a blank.
     */
    public function hasUnknownApprover(): bool
    {
        return $this->status === SubmissionStatus::Approved
            && $this->decided_by === null;
    }

    /** The owner may edit the underlying record only in these two states. */
    public function isEditableByOwner(): bool
    {
        return in_array($this->status, [
            SubmissionStatus::Draft,
            SubmissionStatus::RevisionRequested,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            SubmissionStatus::Rejected,
            SubmissionStatus::Withdrawn,
            SubmissionStatus::Superseded,
        ], true);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', SubmissionStatus::Approved->value);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SubmissionStatus::Submitted->value,
            SubmissionStatus::UnderReview->value,
        ]);
    }

    public function scopeForFacultyQueue(Builder $query, int $facultyId): Builder
    {
        return $query->where('faculty_id_at_submission', $facultyId);
    }
}
