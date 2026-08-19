<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffAffiliation extends Model
{
    protected $fillable = ['staff_profile_id', 'faculty_id', 'department_id', 'research_group_id', 'valid_from', 'valid_to', 'is_primary', 'transfer_reason', 'recorded_by'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_to' => 'date', 'is_primary' => 'boolean'];
    }

    public function staffProfile(): BelongsTo { return $this->belongsTo(StaffProfile::class); }
    public function faculty(): BelongsTo { return $this->belongsTo(Faculty::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function researchGroup(): BelongsTo { return $this->belongsTo(ResearchGroup::class); }

    public function isCurrent(): bool { return $this->valid_to === null; }
}
