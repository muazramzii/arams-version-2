<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiProgress extends Model
{
    protected $fillable = ['kpi_target_id', 'kpi_assignment_id', 'achieved_value', 'target_value', 'percentage', 'computed_at'];

    protected function casts(): array
    {
        return ['achieved_value' => 'decimal:2', 'target_value' => 'decimal:2', 'percentage' => 'decimal:2', 'computed_at' => 'datetime'];
    }

    public function target(): BelongsTo { return $this->belongsTo(KpiTarget::class, 'kpi_target_id'); }
    public function assignment(): BelongsTo { return $this->belongsTo(KpiAssignment::class, 'kpi_assignment_id'); }
}
