<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiContribution extends Model
{
    protected $fillable = ['kpi_target_id', 'kpi_assignment_id', 'research_record_id', 'hindex_snapshot_id', 'contributed_value', 'counted_on'];

    protected function casts(): array
    {
        return ['contributed_value' => 'decimal:2', 'counted_on' => 'date'];
    }

    public function target(): BelongsTo { return $this->belongsTo(KpiTarget::class, 'kpi_target_id'); }
    public function assignment(): BelongsTo { return $this->belongsTo(KpiAssignment::class, 'kpi_assignment_id'); }
    public function researchRecord(): BelongsTo { return $this->belongsTo(ResearchRecord::class); }
    public function hindexSnapshot(): BelongsTo { return $this->belongsTo(HindexSnapshot::class); }
}
