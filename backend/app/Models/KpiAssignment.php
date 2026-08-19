<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiAssignment extends Model
{
    protected $fillable = ['kpi_target_id', 'staff_profile_id', 'assigned_by_staff_profile_id', 'assigned_at', 'deadline', 'status', 'closed_at', 'note'];

    protected function casts(): array
    {
        return ['status' => \App\Enums\AssignmentStatus::class, 'assigned_at' => 'datetime', 'deadline' => 'date', 'closed_at' => 'datetime'];
    }

    public function target(): BelongsTo { return $this->belongsTo(KpiTarget::class, 'kpi_target_id'); }
    public function staffProfile(): BelongsTo { return $this->belongsTo(StaffProfile::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(StaffProfile::class, 'assigned_by_staff_profile_id'); }
    public function contributions(): HasMany { return $this->hasMany(KpiContribution::class); }
    public function progress(): HasMany { return $this->hasMany(KpiProgress::class); }
}
