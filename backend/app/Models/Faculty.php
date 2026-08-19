<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faculty extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'name_ms', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function researchGroups(): HasMany { return $this->hasMany(ResearchGroup::class); }
    public function affiliations(): HasMany { return $this->hasMany(StaffAffiliation::class); }
    public function leaders(): HasMany { return $this->hasMany(FacultyLeader::class); }

    /** Current TDPP appointments. Empty means nobody can validate here (D1). */
    public function currentLeaders(): HasMany
    {
        return $this->leaders()->whereNull('valid_to')->whereDate('valid_from', '<=', now());
    }

    public function hasActiveValidator(): bool { return $this->currentLeaders()->exists(); }

    public function researchRecords(): HasMany
    {
        return $this->hasMany(ResearchRecord::class, 'attributed_faculty_id');
    }
}
