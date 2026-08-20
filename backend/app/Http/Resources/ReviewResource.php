<?php

namespace App\Http\Resources;

use App\Enums\ReviewerRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One decision in the validation history.
 *
 * ARAMS 1.0 had no history at all: each decision overwrote status, remarks and
 * validated_at on the parent row, so "who rejected version 1, and why?" was
 * unanswerable the moment version 2 was decided.
 */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isLegacy = $this->reviewer_role === ReviewerRole::AdminLegacy;

        return [
            'id'          => $this->id,
            'revision_no' => $this->revision_no,
            'decision'    => $this->decision->value,
            'remarks'     => $this->remarks,
            'decided_at'  => $this->decided_at?->toIso8601String(),

            'reviewer' => $this->relationLoaded('reviewer') && $this->reviewer ? [
                'id'    => $this->reviewer->id,
                'name'  => $this->reviewer->staffProfile?->full_name ?? $this->reviewer->email,
                'role'  => $this->reviewer_role->value,
            ] : null,

            // Distinguishes "nobody reviewed this" from "we no longer know who
            // did" — the second is true of 108 migrated 1.0 approvals.
            'is_legacy'        => $isLegacy,
            'reviewer_unknown' => $this->reviewer_user_id === null,
        ];
    }
}
