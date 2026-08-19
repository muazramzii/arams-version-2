<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchType extends Model
{
    protected $fillable = ['code', 'label', 'label_ms', 'subtype_table', 'model_class', 'requires_submission', 'effective_date_source', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['requires_submission' => 'boolean', 'is_active' => 'boolean'];
    }

    public function researchRecords(): HasMany { return $this->hasMany(ResearchRecord::class); }
}
