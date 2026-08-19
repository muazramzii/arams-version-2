<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicationIndexing extends Model
{
    protected $fillable = ['research_record_id', 'indexing_id'];

    public function publication(): BelongsTo { return $this->belongsTo(Publication::class, 'research_record_id'); }
    public function indexing(): BelongsTo { return $this->belongsTo(\App\Models\Reference\Indexing::class, 'indexing_id'); }
}
