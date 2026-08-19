<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HindexSnapshot extends Model
{
    use SoftDeletes;

    protected $fillable = ['staff_profile_id', 'metric_source_id', 'record_year', 'effective_date', 'hindex_value', 'citation_count', 'document_count', 'recorded_by', 'recorded_at', 'source_note'];

    protected function casts(): array
    {
        return ['record_year' => 'integer', 'hindex_value' => 'integer', 'citation_count' => 'integer', 'document_count' => 'integer', 'effective_date' => 'date', 'recorded_at' => 'datetime'];
    }

    public function staffProfile(): BelongsTo { return $this->belongsTo(StaffProfile::class); }
    public function source(): BelongsTo { return $this->belongsTo(\App\Models\Reference\MetricSource::class, 'metric_source_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    /**
     * The value at the most recent year, NOT MAX() across all years — the 1.0
     * view vw_lecturer_kpi used MAX(), so a lecturer recorded at 14 in 2024 and
     * 12 in 2025 still showed 14.
     */
    public function scopeLatestForStaff(Builder $query, int $staffProfileId): Builder
    {
        return $query->where('staff_profile_id', $staffProfileId)->orderByDesc('record_year')->limit(1);
    }
}
