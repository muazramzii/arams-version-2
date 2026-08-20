<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResearchRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->whenLoaded('researchType', fn () => $this->researchType->code),
            'display_title' => $this->display_title,

            'effective_date'           => $this->effective_date?->toDateString(),
            'effective_date_precision' => $this->effective_date_precision->value,
            'needs_date_backfill'      => $this->needsDateBackfill(),

            'attributed_faculty_id' => $this->attributed_faculty_id,
            'attribution_basis'     => $this->attribution_basis?->value,
            'attributed_at'         => $this->attributed_at?->toIso8601String(),

            'owner' => $this->whenLoaded('owner', fn () => [
                'id'        => $this->owner->id,
                'full_name' => $this->owner->full_name,
                'staff_no'  => $this->owner->staff_no,
            ]),

            'submission' => $this->whenLoaded('submission', fn () => $this->submission ? [
                'id'               => $this->submission->id,
                'status'           => $this->submission->status->value,
                'current_revision' => $this->submission->current_revision,
                'submitted_at'     => $this->submission->submitted_at?->toIso8601String(),
                'decided_at'       => $this->submission->decided_at?->toIso8601String(),
                'approver_unknown' => $this->submission->hasUnknownApprover(),
                'editable'         => $this->submission->isEditableByOwner(),
                'reviews'          => $this->submission->relationLoaded('reviews')
                    ? ReviewResource::collection($this->submission->reviews)
                    : null,
            ] : null),

            'detail' => $this->when(
                $request->routeIs('*.show'),
                fn () => $this->detail()?->toArray(),
            ),

            'deleted_at'      => $this->deleted_at?->toIso8601String(),
            'deletion_reason' => $this->deletion_reason,
        ];
    }
}
