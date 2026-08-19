<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTarget extends Model
{
    protected $fillable = ['kpi_period_id', 'kpi_measure_id', 'scope_type', 'scope_id', 'target_value', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['scope_type' => \App\Enums\ScopeType::class, 'target_value' => 'decimal:2'];
    }

    public function period(): BelongsTo { return $this->belongsTo(KpiPeriod::class, 'kpi_period_id'); }
    public function measure(): BelongsTo { return $this->belongsTo(KpiMeasure::class, 'kpi_measure_id'); }
    public function criteria(): HasMany { return $this->hasMany(KpiTargetCriterion::class); }
    public function assignments(): HasMany { return $this->hasMany(KpiAssignment::class); }
    public function progress(): HasMany { return $this->hasMany(KpiProgress::class); }
    public function contributions(): HasMany { return $this->hasMany(KpiContribution::class); }
}
