<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrantProject extends Model
{
    use SoftDeletes;

    protected $fillable = ['grant_code', 'title', 'funder_id', 'grant_category_id', 'grant_level_id', 'grant_status_id', 'total_amount', 'currency', 'start_date', 'end_date', 'mygrants_id'];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];
    }

    public function participations(): HasMany { return $this->hasMany(Grant::class); }
    public function incomes(): HasMany { return $this->hasMany(ResearchIncome::class); }
    public function funder(): BelongsTo { return $this->belongsTo(\App\Models\Reference\Funder::class, 'funder_id'); }
    public function level(): BelongsTo { return $this->belongsTo(\App\Models\Reference\GrantLevel::class, 'grant_level_id'); }

    /** 70 of 71 rows in the 1.0 data have no start_date and need backfill. */
    public function needsDateBackfill(): bool { return $this->start_date === null; }
}
