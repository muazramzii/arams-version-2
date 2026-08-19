<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiPeriod extends Model
{
    protected $fillable = ['code', 'label', 'start_date', 'end_date', 'is_locked'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_locked' => 'boolean'];
    }

    public function targets(): HasMany { return $this->hasMany(KpiTarget::class); }

    public function contains(?\DateTimeInterface $date): bool
    {
        if ($date === null) { return false; }
        return $date >= $this->start_date->startOfDay() && $date <= $this->end_date->endOfDay();
    }
}
