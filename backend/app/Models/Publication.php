<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publication extends Model
{
    protected $primaryKey = 'research_record_id';
    public $incrementing = false;

    protected $fillable = ['research_record_id', 'journal_name', 'issn', 'pub_year', 'volume', 'issue', 'pages', 'publication_type_id', 'author_role_id', 'country_id', 'quartile', 'impact_factor', 'doi', 'url', 'student_author', 'national_collaboration', 'international_collaboration', 'industries_collaboration', 'raw_authors'];

    protected function casts(): array
    {
        return ['pub_year' => 'integer', 'impact_factor' => 'decimal:3', 'student_author' => 'boolean', 'national_collaboration' => 'boolean', 'international_collaboration' => 'boolean', 'industries_collaboration' => 'boolean'];
    }

    public function researchRecord(): BelongsTo { return $this->belongsTo(ResearchRecord::class, 'research_record_id'); }
    public function authors(): HasMany { return $this->hasMany(PublicationAuthor::class, 'research_record_id')->orderBy('author_order'); }
    public function indexings(): HasMany { return $this->hasMany(PublicationIndexing::class, 'research_record_id'); }
    public function publicationType(): BelongsTo { return $this->belongsTo(\App\Models\Reference\PublicationType::class, 'publication_type_id'); }
    public function authorRole(): BelongsTo { return $this->belongsTo(\App\Models\Reference\AuthorRole::class, 'author_role_id'); }

    /**
     * Replaces the 1.0 KPI matcher's \`indexing_type = ?\` comparison against a
     * SET column, which silently missed every publication indexed 'Scopus,WoS'.
     */
    public function scopeIndexedIn(Builder $query, string $code): Builder
    {
        return $query->whereHas('indexings.indexing', fn (Builder $q) => $q->where('code', $code));
    }
}
