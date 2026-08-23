<?php

namespace App\Services\Kpi;

use App\Enums\DatePrecision;
use App\Enums\KpiSourceKind;
use App\Models\KpiAssignment;
use App\Models\KpiContribution;
use App\Models\KpiProgress;
use App\Models\KpiTarget;
use App\Models\ResearchRecord;
use Illuminate\Support\Facades\DB;

/**
 * D4: credit follows the research record's own effective date.
 *
 * ARAMS 1.0 counted a lecturer's entire career on every approval, because
 * tbl_kpi_task had no period column at all — one live task reads target 1
 * against progress 19. Worse, progress was an incrementing counter that could
 * never fall, so a record later rejected or deleted left the target
 * permanently satisfied.
 *
 * Here progress is *derived* from kpi_contributions and recomputed from
 * scratch, so it is idempotent and can go down.
 */
class KpiProgressCalculator
{
    public function __construct(private readonly CriteriaEvaluator $criteria) {}

    /**
     * Re-evaluate every target this record could contribute to.
     * Safe to call repeatedly — contributions are replaced, not accumulated.
     */
    public function recomputeForRecord(ResearchRecord $record): void
    {
        DB::transaction(function () use ($record) {
            // Clear first, so a record that no longer qualifies loses its credit.
            $affected = KpiContribution::where('research_record_id', $record->id)
                ->pluck('kpi_target_id')
                ->unique();

            KpiContribution::where('research_record_id', $record->id)->delete();

            if ($this->counts($record)) {
                foreach ($this->matchingTargets($record) as $target) {
                    $this->credit($target, $record);
                    $affected->push($target->id);
                }
            }

            foreach ($affected->unique() as $targetId) {
                if ($target = KpiTarget::find($targetId)) {
                    $this->recomputeTarget($target);
                }
            }
        });
    }

    /**
     * A record counts only when approved, not deleted, and placeable in time.
     * The third condition is what keeps the 88 dateless migrated records —
     * 70 grants and all 18 IP records — out of period-scoped figures.
     */
    private function counts(ResearchRecord $record): bool
    {
        return $record->deleted_at === null
            && $record->submission?->status->value === 'APPROVED'
            && $record->effective_date !== null
            && $record->effective_date_precision !== DatePrecision::Unknown;
    }

    /** @return \Illuminate\Support\Collection<int, KpiTarget> */
    private function matchingTargets(ResearchRecord $record)
    {
        $ownerId   = $record->owner_staff_profile_id;
        $facultyId = $record->attributed_faculty_id;

        return KpiTarget::query()
            ->with(['period', 'measure', 'criteria'])
            ->whereHas('measure', fn ($q) => $q
                ->where('source_kind', KpiSourceKind::ResearchRecord->value)
                ->where('research_type_id', $record->research_type_id))
            ->whereHas('period', fn ($q) => $q
                ->whereDate('start_date', '<=', $record->effective_date)
                ->whereDate('end_date', '>=', $record->effective_date))
            ->where(function ($q) use ($ownerId, $facultyId) {
                $q->where('scope_type', 'INSTITUTION')
                  ->orWhere(fn ($s) => $s->where('scope_type', 'FACULTY')->where('scope_id', $facultyId))
                  ->orWhere(fn ($s) => $s->where('scope_type', 'STAFF')->where('scope_id', $ownerId));
            })
            ->get()
            ->filter(fn (KpiTarget $t) => $this->criteria->matches($t, $record));
    }

    private function credit(KpiTarget $target, ResearchRecord $record): void
    {
        $value = $this->contributedValue($target, $record);

        // STAFF-scope targets may also be an explicit TDPP assignment.
        $assignmentId = $target->scope_type->value === 'STAFF'
            ? KpiAssignment::where('kpi_target_id', $target->id)
                ->where('staff_profile_id', $record->owner_staff_profile_id)
                ->value('id')
            : null;

        KpiContribution::updateOrCreate(
            [
                'kpi_target_id'      => $target->id,
                'kpi_assignment_id'  => $assignmentId,
                'research_record_id' => $record->id,
            ],
            [
                'contributed_value' => $value,
                'counted_on'        => $record->effective_date,
            ],
        );
    }

    private function contributedValue(KpiTarget $target, ResearchRecord $record): float
    {
        $measure = $target->measure;

        if ($measure->aggregation->value === 'COUNT') {
            return 1.0;
        }

        $detail = $record->detail();
        $column = $measure->value_column;

        return $detail && $column ? (float) ($detail->{$column} ?? 0) : 0.0;
    }

    /**
     * Scan existing approved work and credit whatever this target should
     * already have counted, then recompute.
     *
     * Needed because recomputeForRecord only runs when a record is approved.
     * A target created afterwards would otherwise start at zero and silently
     * discard a lecturer's existing output for that period — which is not what
     * D4 means. Assigning "3 papers in 2026" halfway through 2026 has to count
     * the two already published.
     *
     * Idempotent: contributions are keyed on (target, assignment, record).
     */
    public function backfillTarget(KpiTarget $target): void
    {
        $target->loadMissing(['period', 'measure', 'criteria', 'assignments']);

        if ($target->measure?->source_kind !== KpiSourceKind::ResearchRecord) {
            $this->recomputeTarget($target);

            return;
        }

        $records = ResearchRecord::query()
            ->countable()
            ->datePlaceable()
            ->whereNull('research_records.deleted_at')
            ->where('research_records.research_type_id', $target->measure->research_type_id)
            ->whereBetween('research_records.effective_date', [
                $target->period->start_date,
                $target->period->end_date,
            ])
            ->when(
                $target->scope_type->value === 'STAFF',
                fn ($q) => $q->where('research_records.owner_staff_profile_id', $target->scope_id),
            )
            ->when(
                $target->scope_type->value === 'FACULTY',
                fn ($q) => $q->where('research_records.attributed_faculty_id', $target->scope_id),
            )
            ->with(['researchType', 'publication.indexings.indexing'])
            ->get();

        DB::transaction(function () use ($target, $records) {
            foreach ($records as $record) {
                if ($this->criteria->matches($target, $record)) {
                    $this->credit($target, $record);
                }
            }

            $this->recomputeTarget($target);
        });
    }

    /** Recompute a target's progress from its contributions. */
    public function recomputeTarget(KpiTarget $target): void
    {
        // Every contribution to this target, whether or not it also belongs to
        // an assignment. The assignment rows below are subsets of this total —
        // excluding them here made an assigned target report zero achievement.
        $achieved = (float) KpiContribution::where('kpi_target_id', $target->id)
            ->sum('contributed_value');

        $this->writeProgress($target, null, $achieved);

        foreach ($target->assignments as $assignment) {
            $value = (float) KpiContribution::where('kpi_assignment_id', $assignment->id)
                ->sum('contributed_value');

            $this->writeProgress($target, $assignment, $value);
            $this->settleAssignment($assignment, $value, (float) $target->target_value);
        }
    }

    private function writeProgress(KpiTarget $target, ?KpiAssignment $assignment, float $achieved): void
    {
        $targetValue = (float) $target->target_value;

        KpiProgress::updateOrCreate(
            ['kpi_target_id' => $target->id, 'kpi_assignment_id' => $assignment?->id],
            [
                'achieved_value' => $achieved,
                'target_value'   => $targetValue,
                'percentage'     => $targetValue > 0
                    ? round(min($achieved / $targetValue * 100, 999.99), 2)
                    : 0,
                'computed_at'    => now(),
            ],
        );
    }

    /**
     * Assignment status is derived too, so it reverts if credit is withdrawn.
     * 'Met late' is judged against the deadline, not against approval date.
     */
    private function settleAssignment(KpiAssignment $assignment, float $achieved, float $targetValue): void
    {
        if ($achieved < $targetValue) {
            $overdue = $assignment->deadline && $assignment->deadline->isPast();

            $assignment->update([
                'status'    => $overdue ? 'MISSED' : 'OPEN',
                'closed_at' => null,
            ]);

            return;
        }

        $late = $assignment->deadline && $assignment->deadline->isPast();

        $assignment->update([
            'status'    => $late ? 'MET_LATE' : 'MET',
            'closed_at' => $assignment->closed_at ?? now(),
        ]);
    }
}
