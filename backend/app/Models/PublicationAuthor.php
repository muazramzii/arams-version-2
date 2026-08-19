<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicationAuthor extends Model
{
    protected $fillable = ['research_record_id', 'staff_profile_id', 'person_name', 'author_order', 'is_corresponding', 'is_student', 'affiliation_text'];

    protected function casts(): array
    {
        return ['author_order' => 'integer', 'is_corresponding' => 'boolean', 'is_student' => 'boolean'];
    }

    public function publication(): BelongsTo { return $this->belongsTo(Publication::class, 'research_record_id'); }
    public function staffProfile(): BelongsTo { return $this->belongsTo(StaffProfile::class); }

    public function isInternal(): bool { return $this->staff_profile_id !== null; }
}
