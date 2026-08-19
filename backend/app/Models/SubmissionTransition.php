<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionTransition extends Model
{
    protected $fillable = ['from_status', 'to_status', 'actor', 'requires_remarks', 'label'];

    protected function casts(): array
    {
        return ['requires_remarks' => 'boolean'];
    }

    public static function isAllowed(string $from, string $to, string $actor): bool
    {
        return static::where('from_status', $from)->where('to_status', $to)->where('actor', $actor)->exists();
    }
}
