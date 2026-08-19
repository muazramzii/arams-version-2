<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDefinition extends Model
{
    protected $fillable = ['code', 'title', 'description', 'parameter_schema', 'minimum_role', 'is_active'];

    protected function casts(): array
    {
        return ['parameter_schema' => 'array', 'is_active' => 'boolean'];
    }

    public function runs(): HasMany { return $this->hasMany(ReportRun::class); }
}
