<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacultyLeader extends Model
{
    protected $fillable = ['faculty_id', 'staff_profile_id', 'appointment', 'valid_from', 'valid_to', 'appointed_by', 'note'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date'];
    }

    public function faculty(): BelongsTo { return $this->belongsTo(Faculty::class); }
    public function staffProfile(): BelongsTo { return $this->belongsTo(StaffProfile::class); }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('valid_to')->whereDate('valid_from', '<=', now());
    }
}
