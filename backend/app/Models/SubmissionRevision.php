<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionRevision extends Model
{
    protected $fillable = ['submission_id', 'revision_no', 'payload', 'submitted_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'submitted_at' => 'datetime', 'revision_no' => 'integer'];
    }

    public function submission(): BelongsTo { return $this->belongsTo(Submission::class); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
}
