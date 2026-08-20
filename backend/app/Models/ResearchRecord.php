<?php

namespace App\Models;

use App\Enums\AttributionBasis;
use App\Enums\DatePrecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The domain supertype. Every research record is one row here plus exactly one
 * row in a subtype table (publications, grants, ip_records, research_incomes,
 * awards).
 *
 * Carries no workflow state — that lives in Submission, which points back here
 * one-to-one. D6 extensibility comes from research_type_id: adding Research
 * Projects later is one reference row plus one subtype table, with submissions,
 * KPI, audit and analytics untouched.
 */
class ResearchRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'research_type_id', 'owner_staff_profile_id', 'display_title',
        'effective_date', 'effective_date_precision',
        'attributed_faculty_id', 'attributed_at', 'attribution_basis',
        'deleted_by', 'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_date'           => 'date',
            'effective_date_precision' => DatePrecision::class,
            'attribution_basis'        => AttributionBasis::class,
            'attributed_at'            => 'datetime',
        ];
    }

    public function researchType(): BelongsTo
    {
        return $this->belongsTo(ResearchType::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'owner_staff_profile_id');
    }

    public function attributedFaculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'attributed_faculty_id');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(Submission::class);
    }

    public function kpiContributions(): HasMany
    {
        return $this->hasMany(KpiContribution::class);
    }

    // ── Subtypes. Exactly one is present, per research_type_id. ──
    public function publication(): HasOne
    {
        return $this->hasOne(Publication::class, 'research_record_id');
    }

    public function grant(): HasOne
    {
        return $this->hasOne(Grant::class, 'research_record_id');
    }

    public function ipRecord(): HasOne
    {
        return $this->hasOne(IpRecord::class, 'research_record_id');
    }

    public function researchIncome(): HasOne
    {
        return $this->hasOne(ResearchIncome::class, 'research_record_id');
    }

    public function award(): HasOne
    {
        return $this->hasOne(Award::class, 'research_record_id');
    }

    /** The subtype row, resolved through the research type registry. */
    public function detail(): ?Model
    {
        $class = $this->researchType?->model_class;

        return $class ? $class::find($this->getKey()) : null;
    }

    /**
     * The single definition of "counts toward official figures".
     *
     * ARAMS 1.0 retyped this rule as a WHERE clause in roughly forty places.
     * It was correct in all of them, which was luck rather than design.
     */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->whereHas(
            'submission',
            fn (Builder $q) => $q->where('status', 'APPROVED')
        );
    }

    /**
     * D4: only records with a known effective date can be placed in a period.
     * 88 records migrate as UNKNOWN — 70 of 71 grants have no start_date and
     * all 18 IP records have neither filing_date nor grant_date.
     */
    public function scopeDatePlaceable(Builder $query): Builder
    {
        // Columns are table-qualified throughout: analytics joins subtype
        // tables, and `grants` also carries owner_staff_profile_id, so an
        // unqualified name is ambiguous the moment a join appears.
        return $query->where('research_records.effective_date_precision', '!=', DatePrecision::Unknown->value)
                     ->whereNotNull('research_records.effective_date');
    }

    public function scopeInPeriod(Builder $query, KpiPeriod $period): Builder
    {
        return $query->datePlaceable()
            ->whereBetween('research_records.effective_date', [$period->start_date, $period->end_date]);
    }

    public function scopeForFaculty(Builder $query, int $facultyId): Builder
    {
        return $query->where('research_records.attributed_faculty_id', $facultyId);
    }

    public function scopeOwnedBy(Builder $query, ?int $staffProfileId): Builder
    {
        return $query->where('research_records.owner_staff_profile_id', $staffProfileId ?? 0);
    }

    public function needsDateBackfill(): bool
    {
        return $this->effective_date_precision === DatePrecision::Unknown;
    }
}
