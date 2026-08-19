<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpRecord extends Model
{
    protected $primaryKey = 'research_record_id';
    public $incrementing = false;

    protected $fillable = ['research_record_id', 'ip_type_id', 'ip_registration_status_id', 'country_id', 'ip_number', 'filing_date', 'grant_date', 'raw_inventors'];

    protected function casts(): array
    {
        return ['filing_date' => 'date', 'grant_date' => 'date'];
    }

    public function researchRecord(): BelongsTo { return $this->belongsTo(ResearchRecord::class, 'research_record_id'); }
    public function inventors(): HasMany { return $this->hasMany(IpInventor::class, 'research_record_id')->orderBy('inventor_order'); }
    public function ipType(): BelongsTo { return $this->belongsTo(\App\Models\Reference\IpType::class, 'ip_type_id'); }
}
