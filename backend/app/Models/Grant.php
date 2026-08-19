<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grant extends Model
{
    protected $primaryKey = 'research_record_id';
    public $incrementing = false;

    protected $fillable = ['research_record_id', 'grant_project_id', 'grant_role_id', 'allocated_amount', 'owner_staff_profile_id'];

    protected function casts(): array
    {
        return ['allocated_amount' => 'decimal:2'];
    }

    public function researchRecord(): BelongsTo { return $this->belongsTo(ResearchRecord::class, 'research_record_id'); }
    public function project(): BelongsTo { return $this->belongsTo(GrantProject::class, 'grant_project_id'); }
    public function role(): BelongsTo { return $this->belongsTo(\App\Models\Reference\GrantRole::class, 'grant_role_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(StaffProfile::class, 'owner_staff_profile_id'); }
}
