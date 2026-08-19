<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionReview extends Model
{
    protected $fillable = ['submission_id', 'revision_no', 'reviewer_user_id', 'reviewer_role', 'decision', 'remarks', 'decided_at', 'origin'];

    protected function casts(): array
    {
        return ['decision' => \App\Enums\ReviewDecision::class, 'reviewer_role' => \App\Enums\ReviewerRole::class, 'origin' => \App\Enums\RecordOrigin::class, 'decided_at' => 'datetime', 'revision_no' => 'integer'];
    }

    public function submission(): BelongsTo { return $this->belongsTo(Submission::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_user_id'); }

    /** Append-only: block updates and deletes at the model layer too. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('submission_reviews is append-only'));
        static::deleting(fn () => throw new \RuntimeException('submission_reviews is append-only'));
    }
}
