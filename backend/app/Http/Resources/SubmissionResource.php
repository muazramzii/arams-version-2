<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Not whenLoaded(): that yields a MissingValue object rather than
        // null, which quietly satisfies a null check further down.
        $record = $this->relationLoaded('researchRecord') ? $this->researchRecord : null;

        return [
            'id'               => $this->id,
            'status'           => $this->status->value,
            'current_revision' => $this->current_revision,
            'origin'           => $this->origin->value,

            'submitted_at'       => $this->submitted_at?->toIso8601String(),
            'first_submitted_at' => $this->first_submitted_at?->toIso8601String(),
            'decided_at'         => $this->decided_at?->toIso8601String(),
            'claimed_at'         => $this->claimed_at?->toIso8601String(),

            'faculty_id_at_submission' => $this->faculty_id_at_submission,

            /**
             * Migrated 1.0 approvals have no recorded approver — 108 of 272.
             * The loss is permanent, so the API states it explicitly rather
             * than sending a null the UI would render as a blank field.
             */
            'approver_unknown' => $this->hasUnknownApprover(),

            'record' => $this->when($record !== null, fn () => [
                'id'            => $record->id,
                'type'          => $record->researchType?->code,
                'display_title' => $record->display_title,
                'effective_date' => $record->effective_date?->toDateString(),
                'effective_date_precision' => $record->effective_date_precision->value,
                // Flags the 88 dateless migrated records for the backfill worklist.
                'needs_date_backfill'      => $record->needsDateBackfill(),
                'attributed_faculty_id'    => $record->attributed_faculty_id,
                'owner' => $record->relationLoaded('owner') && $record->owner ? [
                    'id'        => $record->owner->id,
                    'full_name' => $record->owner->full_name,
                    'staff_no'  => $record->owner->staff_no,
                ] : null,
            ]),

            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),

            'revisions' => $this->whenLoaded('revisions', fn () => $this->revisions->map(fn ($r) => [
                'revision_no'  => $r->revision_no,
                'submitted_at' => $r->submitted_at?->toIso8601String(),
            ])),
        ];
    }
}
