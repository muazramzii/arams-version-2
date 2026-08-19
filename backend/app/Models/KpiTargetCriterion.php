<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTargetCriterion extends Model
{
    protected $fillable = ['kpi_target_id', 'field_path', 'operator', 'value'];

    public function target(): BelongsTo { return $this->belongsTo(KpiTarget::class, 'kpi_target_id'); }
}
