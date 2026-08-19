<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportRun extends Model
{
    protected $fillable = ['report_definition_id', 'requested_by', 'parameters', 'scope_type', 'scope_id', 'format', 'status', 'row_count', 'file_path', 'file_hash', 'generated_at', 'expires_at'];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'scope_type' => \App\Enums\ScopeType::class, 'generated_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function definition(): BelongsTo { return $this->belongsTo(ReportDefinition::class, 'report_definition_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
