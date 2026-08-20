<?php

namespace App\Services\Analytics;

use App\Models\Faculty;
use App\Models\KpiPeriod;
use App\Models\ResearchRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every metric has one definition here.
 *
 * ARAMS 1.0 recomputed "total publications" independently in at least six
 * files, each retyping the same approved-only WHERE clause. It happened to be
 * correct everywhere, which was luck rather than design.
 */
class AnalyticsService
{
    /**
     * The base query: approved, not deleted, and scoped to what this user may
     * see. Everything below builds on it, so scope cannot be forgotten.
     */
    private function base(AnalyticsScope $scope): Builder
    {
        $query = ResearchRecord::query()->countable();

        if ($scope->isStaff()) {
            $query->ownedBy($scope->staffProfileId);
        } elseif ($scope->isFaculty()) {
            $query->whereIn('research_records.attributed_faculty_id', $scope->facultyIds ?: [0]);
        }

        return $query;
    }

    /** Headline counts for a dashboard. */
    public function overview(AnalyticsScope $scope, ?KpiPeriod $period = null): array
    {
        $query = $this->base($scope);

        if ($period) {
            $query->inPeriod($period);
        }

        $byType = (clone $query)
            ->join('research_types', 'research_types.id', '=', 'research_records.research_type_id')
            ->groupBy('research_types.code')
            ->pluck(DB::raw('COUNT(*)'), 'research_types.code')
            ->all();

        return [
            'scope'  => $scope->level,
            'period' => $period?->code,
            'totals' => [
                'records'      => (clone $query)->count(),
                'publications' => $byType['PUBLICATION'] ?? 0,
                'grants'       => $byType['GRANT'] ?? 0,
                'ip_records'   => $byType['IP_RECORD'] ?? 0,
                'income'       => $byType['RESEARCH_INCOME'] ?? 0,
                'awards'       => $byType['AWARD'] ?? 0,
            ],
            'grant_value'  => $this->sumGrantValue($scope, $period),
            'income_total' => $this->sumIncome($scope, $period),
            'latest_hindex' => $scope->isStaff() ? $this->latestHindex($scope->staffProfileId) : null,
            // Honest about what is not yet measurable.
            'data_quality' => [
                'records_missing_effective_date' => (clone $this->base($scope))
                    ->where('research_records.effective_date_precision', 'UNKNOWN')->count(),
            ],
        ];
    }

    /** Output by year — the trend chart. */
    public function trends(AnalyticsScope $scope, ?string $typeCode = null): array
    {
        $query = $this->base($scope)->datePlaceable();

        if ($typeCode) {
            $query->whereHas('researchType', fn ($q) => $q->where('code', $typeCode));
        }

        return $query
            ->selectRaw('YEAR(research_records.effective_date) AS year, COUNT(*) AS total')
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(fn ($r) => ['year' => (int) $r->year, 'total' => (int) $r->total])
            ->all();
    }

    /**
     * Counts grouped by a dimension. Only whitelisted dimensions are accepted —
     * the column never comes from the request.
     */
    public function breakdown(AnalyticsScope $scope, string $dimension, ?KpiPeriod $period = null): array
    {
        $query = $this->base($scope);

        if ($period) {
            $query->inPeriod($period);
        }

        return match ($dimension) {
            'quartile' => $query
                ->join('publications', 'publications.research_record_id', '=', 'research_records.id')
                ->groupBy('publications.quartile')
                ->pluck(DB::raw('COUNT(*)'), 'publications.quartile')->all(),

            'indexing' => $query
                ->join('publication_indexings', 'publication_indexings.research_record_id', '=', 'research_records.id')
                ->join('indexings', 'indexings.id', '=', 'publication_indexings.indexing_id')
                ->groupBy('indexings.label')
                ->pluck(DB::raw('COUNT(*)'), 'indexings.label')->all(),

            'publication_type' => $query
                ->join('publications', 'publications.research_record_id', '=', 'research_records.id')
                ->leftJoin('publication_types', 'publication_types.id', '=', 'publications.publication_type_id')
                ->groupBy('publication_types.label')
                ->pluck(DB::raw('COUNT(*)'), 'publication_types.label')->all(),

            'grant_level' => $query
                ->join('grants', 'grants.research_record_id', '=', 'research_records.id')
                ->join('grant_projects', 'grant_projects.id', '=', 'grants.grant_project_id')
                ->leftJoin('grant_levels', 'grant_levels.id', '=', 'grant_projects.grant_level_id')
                ->groupBy('grant_levels.label')
                ->pluck(DB::raw('COUNT(*)'), 'grant_levels.label')->all(),

            'grant_role' => $query
                ->join('grants', 'grants.research_record_id', '=', 'research_records.id')
                ->join('grant_roles', 'grant_roles.id', '=', 'grants.grant_role_id')
                ->groupBy('grant_roles.label')
                ->pluck(DB::raw('COUNT(*)'), 'grant_roles.label')->all(),

            'faculty' => $query
                ->join('faculties', 'faculties.id', '=', 'research_records.attributed_faculty_id')
                ->groupBy('faculties.code')
                ->pluck(DB::raw('COUNT(*)'), 'faculties.code')->all(),

            'research_type' => $query
                ->join('research_types', 'research_types.id', '=', 'research_records.research_type_id')
                ->groupBy('research_types.label')
                ->pluck(DB::raw('COUNT(*)'), 'research_types.label')->all(),

            default => throw new \InvalidArgumentException("Unknown dimension: {$dimension}"),
        };
    }

    /**
     * D5 — anonymised institutional benchmarking.
     *
     * A TDPP sees their own faculty's figure against an institution median,
     * never another faculty's identity or value. With only six faculties
     * currently reporting, a median over a cohort of two would let a requester
     * subtract their own number and read the other exactly, so a value is
     * released only when enough other faculties report.
     */
    public function benchmark(
        AnalyticsScope $scope,
        int $facultyId,
        ?KpiPeriod $period = null,
        int $minCohort = 3,
    ): array {
        if (! $scope->canSeeFaculty($facultyId)) {
            throw new \RuntimeException('You may not benchmark that faculty.');
        }

        $counts = ResearchRecord::query()
            ->countable()
            ->when($period, fn ($q) => $q->inPeriod($period))
            ->whereNotNull('research_records.attributed_faculty_id')
            ->selectRaw('research_records.attributed_faculty_id AS attributed_faculty_id, COUNT(*) AS total')
            ->groupBy('research_records.attributed_faculty_id')
            ->pluck('total', 'attributed_faculty_id');

        $mine   = (int) ($counts[$facultyId] ?? 0);
        $others = $counts->except($facultyId)->values();

        if ($others->count() < $minCohort) {
            return [
                'faculty_id'   => $facultyId,
                'your_value'   => $mine,
                'comparison'   => null,
                'suppressed'   => true,
                'reason'       => 'Not enough faculties are reporting for this measure to compare '
                                . "without identifying them ({$others->count()} of {$minCohort} needed).",
            ];
        }

        $sorted = $others->sort()->values();
        $count  = $sorted->count();
        $median = $count % 2
            ? $sorted[intdiv($count, 2)]
            : ($sorted[intdiv($count, 2) - 1] + $sorted[intdiv($count, 2)]) / 2;

        return [
            'faculty_id'        => $facultyId,
            'your_value'        => $mine,
            'institution_median' => round((float) $median, 2),
            'cohort_size'       => $count,
            'suppressed'        => false,
            // Deliberately no per-faculty values and no faculty names.
        ];
    }

    private function sumGrantValue(AnalyticsScope $scope, ?KpiPeriod $period): float
    {
        return (float) $this->base($scope)
            ->when($period, fn ($q) => $q->inPeriod($period))
            ->join('grants', 'grants.research_record_id', '=', 'research_records.id')
            ->sum('grants.allocated_amount');
    }

    private function sumIncome(AnalyticsScope $scope, ?KpiPeriod $period): float
    {
        return (float) $this->base($scope)
            ->when($period, fn ($q) => $q->inPeriod($period))
            ->join('research_incomes', 'research_incomes.research_record_id', '=', 'research_records.id')
            ->sum('research_incomes.amount');
    }

    /**
     * The value at the most recent year, not MAX() across all years — the 1.0
     * view vw_lecturer_kpi used MAX(), so a researcher recorded at 14 in 2024
     * and 12 in 2025 still displayed 14.
     */
    private function latestHindex(?int $staffProfileId): ?array
    {
        if ($staffProfileId === null) {
            return null;
        }

        $row = DB::table('hindex_snapshots')
            ->where('staff_profile_id', $staffProfileId)
            ->whereNull('deleted_at')
            ->orderByDesc('record_year')
            ->first(['hindex_value', 'citation_count', 'record_year']);

        return $row ? [
            'value'       => (int) $row->hindex_value,
            'citations'   => $row->citation_count !== null ? (int) $row->citation_count : null,
            'record_year' => (int) $row->record_year,
        ] : null;
    }

    /** Faculties this scope is allowed to name, for filter dropdowns. */
    public function visibleFaculties(AnalyticsScope $scope): array
    {
        return Faculty::query()
            ->when(! $scope->isInstitution(), fn ($q) => $q->whereIn('id', $scope->facultyIds ?: [0]))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->all();
    }
}
