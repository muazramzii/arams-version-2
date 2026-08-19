<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Award extends Model
{
    protected $primaryKey = 'research_record_id';
    public $incrementing = false;

    protected $fillable = ['research_record_id', 'award_type_id', 'award_level_id', 'organiser', 'award_year'];

    protected function casts(): array
    {
        return ['award_year' => 'integer'];
    }

    public function researchRecord(): BelongsTo { return $this->belongsTo(ResearchRecord::class, 'research_record_id'); }
    public function awardType(): BelongsTo { return $this->belongsTo(\App\Models\Reference\AwardType::class, 'award_type_id'); }
    public function level(): BelongsTo { return $this->belongsTo(\App\Models\Reference\AwardLevel::class, 'award_level_id'); }
}
