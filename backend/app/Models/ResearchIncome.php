<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchIncome extends Model
{
    protected $primaryKey = 'research_record_id';
    public $incrementing = false;

    protected $fillable = ['research_record_id', 'grant_project_id', 'income_category_id', 'source_name', 'amount', 'currency', 'year_received', 'received_on'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'year_received' => 'integer', 'received_on' => 'date'];
    }

    public function researchRecord(): BelongsTo { return $this->belongsTo(ResearchRecord::class, 'research_record_id'); }
    public function grantProject(): BelongsTo { return $this->belongsTo(GrantProject::class); }
    public function category(): BelongsTo { return $this->belongsTo(\App\Models\Reference\IncomeCategory::class, 'income_category_id'); }
}
