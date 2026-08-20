<?php

namespace App\Services\Submission;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionTransition;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Guards every status change.
 *
 * Legal moves live in submission_transitions as data, so they are inspectable
 * and testable in one place rather than scattered through controllers — which
 * is where ARAMS 1.0 kept them, when it kept them at all: api/validate.php
 * would happily re-approve an already-approved record, overwriting its remarks
 * and validated_at with no record that it had happened.
 */
class SubmissionStateMachine
{
    /** Actor roles as recorded in submission_transitions. */
    public const ACTOR_OWNER = 'OWNER';
    public const ACTOR_TDPP  = 'TDPP';
    public const ACTOR_ADMIN = 'ADMIN';

    /** Which actor label applies to this user for this submission. */
    public function actorFor(User $user, Submission $submission): ?string
    {
        if ($submission->submitted_by === $user->id) {
            return self::ACTOR_OWNER;
        }

        if ($user->isTdpp()) {
            return self::ACTOR_TDPP;
        }

        if ($user->isAdmin()) {
            return self::ACTOR_ADMIN;
        }

        return null;
    }

    /** Transitions this user may perform on this submission right now. */
    public function availableTo(User $user, Submission $submission): array
    {
        $actor = $this->actorFor($user, $submission);

        if ($actor === null) {
            return [];
        }

        // A reviewer may never decide on their own submission — even when the
        // same person holds a TDPP appointment for the faculty.
        if ($actor === self::ACTOR_OWNER) {
            return $this->transitions()
                ->where('from_status', $submission->status->value)
                ->where('actor', self::ACTOR_OWNER)
                ->values()
                ->all();
        }

        if ($actor === self::ACTOR_TDPP
            && ! $user->canValidateForFaculty((int) $submission->faculty_id_at_submission)) {
            return [];
        }

        return $this->transitions()
            ->where('from_status', $submission->status->value)
            ->where('actor', $actor)
            ->values()
            ->all();
    }

    public function canTransition(User $user, Submission $submission, SubmissionStatus $to): bool
    {
        foreach ($this->availableTo($user, $submission) as $transition) {
            if ($transition['to_status'] === $to->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate a proposed move, throwing with a reason if it is not allowed.
     * Returns the matched transition definition.
     */
    public function assertTransition(
        User $user,
        Submission $submission,
        SubmissionStatus $to,
        ?string $remarks = null,
    ): array {
        $match = null;

        foreach ($this->availableTo($user, $submission) as $transition) {
            if ($transition['to_status'] === $to->value) {
                $match = $transition;
                break;
            }
        }

        if ($match === null) {
            throw new RuntimeException(sprintf(
                'Cannot move submission from %s to %s as this user.',
                $submission->status->value,
                $to->value,
            ));
        }

        if ($match['requires_remarks'] && trim((string) $remarks) === '') {
            throw new RuntimeException(
                'Remarks are required when rejecting or requesting a revision.'
            );
        }

        return $match;
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function transitions()
    {
        return Cache::remember(
            'submission_transitions',
            now()->addHour(),
            fn () => SubmissionTransition::query()
                ->get(['from_status', 'to_status', 'actor', 'requires_remarks', 'label'])
                ->map(fn ($t) => [
                    'from_status'      => $t->from_status,
                    'to_status'        => $t->to_status,
                    'actor'            => $t->actor,
                    'requires_remarks' => (bool) $t->requires_remarks,
                    'label'            => $t->label,
                ])
        );
    }
}
