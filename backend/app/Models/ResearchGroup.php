<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchGroup extends Model
{
    use SoftDeletes;

    protected $fillable = ['faculty_id', 'research_group_category_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function faculty(): BelongsTo { return $this->belongsTo(Faculty::class); }
    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Reference\ResearchGroupCategory::class, 'research_group_category_id');
    }
}
