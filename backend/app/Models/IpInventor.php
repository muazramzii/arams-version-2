<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpInventor extends Model
{
    protected $fillable = ['research_record_id', 'staff_profile_id', 'person_name', 'inventor_order', 'affiliation_text'];

    protected function casts(): array
    {
        return ['inventor_order' => 'integer'];
    }

    public function ipRecord(): BelongsTo { return $this->belongsTo(IpRecord::class, 'research_record_id'); }
    public function staffProfile(): BelongsTo { return $this->belongsTo(StaffProfile::class); }
}
