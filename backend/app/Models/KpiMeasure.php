<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiMeasure extends Model
{
    protected $fillable = ['code', 'label', 'source_kind', 'research_type_id', 'aggregation', 'value_column', 'unit', 'is_active'];

    protected function casts(): array
    {
        return ['source_kind' => \App\Enums\KpiSourceKind::class, 'aggregation' => \App\Enums\KpiAggregation::class, 'is_active' => 'boolean'];
    }

    public function researchType(): BelongsTo { return $this->belongsTo(ResearchType::class); }
    public function targets(): HasMany { return $this->hasMany(KpiTarget::class); }
}
