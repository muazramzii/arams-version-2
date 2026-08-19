<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffExternalId extends Model
{
    protected $fillable = ['staff_profile_id', 'external_id_provider_id', 'value', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function staffProfile(): BelongsTo { return $this->belongsTo(StaffProfile::class); }
    public function provider(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Reference\ExternalIdProvider::class, 'external_id_provider_id');
    }
}
