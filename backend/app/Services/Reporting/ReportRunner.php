<?php

namespace App\Services\Reporting;

use App\Models\KpiPeriod;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\ResearchRecord;
use App\Models\User;
use App\Services\Analytics\AnalyticsScope;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Generates a report as a stored artifact.
 *
 * Two rules from the audit are enforced here rather than left to callers:
 * approved data only unless explicitly overridden, and scope applied at
 * generation so the artifact is permanently bound to it. ARAMS 1.0 logged that
 * a report had been produced but kept no artifact, and 52 of its 57 log rows
 * recorded an empty report_type because the code wrote values the ENUM did not
 * contain.
 */
class ReportRunner
{
    /** Formats that actually produce a file today. */
    public const SUPPORTED_FORMATS = ['CSV'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function run(
        ReportDefinition $definition,
        User $user,
        AnalyticsScope $scope,
        array $parameters,
        string $format,
    ): ReportRun {
        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new RuntimeException(
                "The {$format} format is not available yet. Use CSV, or export the "
                . 'CSV and convert it.'
            );
        }

        $run = ReportRun::create([
            'report_definition_id' => $definition->id,
            'requested_by'         => $user->id,
            'parameters'           => $parameters,
            'scope_type'           => $scope->level,
            'scope_id'             => $scope->isFaculty()
                ? ($scope->facultyIds[0] ?? null)
                : ($scope->isStaff() ? $scope->staffProfileId : null),
            'format'               => $format,
            'status'               => 'RUNNING',
        ]);

        try {
            [$headers, $rows] = $this->build($definition->code, $scope, $parameters);

            $csv = $this->toCsv($headers, $rows, $definition, $parameters, $scope);
            $path = sprintf('reports/%d-%s-%s.csv', $run->id, $definition->code, now()->format('Ymd-His'));

            Storage::disk('local')->put($path, $csv);

            $run->update([
                'status'       => 'READY',
                'row_count'    => count($rows),
                'file_path'    => $path,
                'file_hash'    => hash('sha256', $csv),
                'generated_at' => now(),
                'expires_at'   => now()->addDays(30),
            ]);

            $this->audit->log(AuditLogger::REPORT_GENERATED, $run, null, [
                'definition' => $definition->code,
                'rows'       => count($rows),
                'scope'      => $scope->level,
            ]);
        } catch (\Throwable $e) {
            $run->update(['status' => 'FAILED']);
            throw $e;
        }

        return $run->fresh();
    }

    /** @return array{0: array<string>, 1: array<int, array<int, mixed>>} */
    private function build(string $code, AnalyticsScope $scope, array $parameters): array
    {
        $query = ResearchRecord::query()
            ->with(['owner', 'attributedFaculty', 'researchType'])
            ->when(
                // Approved-only is the default; anything else is deliberate.
                ! ($parameters['include_unvalidated'] ?? false),
                fn (Builder $q) => $q->countable(),
            );

        if ($scope->isStaff()) {
            $query->ownedBy($scope->staffProfileId);
        } elseif ($scope->isFaculty()) {
            $query->whereIn('research_records.attributed_faculty_id', $scope->facultyIds ?: [0]);
        }

        if (! empty($parameters['period_id']) && $period = KpiPeriod::find($parameters['period_id'])) {
            $query->inPeriod($period);
        }

        if (! empty($parameters['faculty_id']) && $scope->canSeeFaculty((int) $parameters['faculty_id'])) {
            $query->where('research_records.attributed_faculty_id', $parameters['faculty_id']);
        }

        $typeCode = match ($code) {
            'PUBLICATIONS'    => 'PUBLICATION',
            'GRANTS'          => 'GRANT',
            'RESEARCH_INCOME' => 'RESEARCH_INCOME',
            'IP'              => 'IP_RECORD',
            'AWARDS'          => 'AWARD',
            default           => null,
        };

        if ($typeCode) {
            $query->whereHas('researchType', fn (Builder $q) => $q->where('code', $typeCode));
        }

        if ($code === 'DATA_QUALITY') {
            $query->where('research_records.effective_date_precision', 'UNKNOWN');
        }

        $headers = ['#', 'Researcher', 'Staff No', 'Faculty', 'Type', 'Title',
                    'Effective Date', 'Date Precision', 'Status'];

        $rows = $query->orderBy('research_records.effective_date')->get()->values()
            ->map(fn (ResearchRecord $r, int $i) => [
                $i + 1,
                $r->owner?->full_name,
                $r->owner?->staff_no,
                $r->attributedFaculty?->code,
                $r->researchType?->label,
                $r->display_title,
                $r->effective_date?->toDateString() ?? '',
                $r->effective_date_precision->value,
                $r->submission?->status->value ?? 'NONE',
            ])
            ->all();

        return [$headers, $rows];
    }

    /**
     * Provenance is written into the file itself, so a printed report can be
     * traced back to the query that produced it.
     */
    private function toCsv(
        array $headers,
        array $rows,
        ReportDefinition $definition,
        array $parameters,
        AnalyticsScope $scope,
    ): string {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['ARAMS 2.0 — ' . $definition->title]);
        fputcsv($handle, ['Generated', now()->toDayDateTimeString() . ' (Asia/Kuala_Lumpur)']);
        fputcsv($handle, ['Scope', $scope->level]);
        fputcsv($handle, ['Rows', count($rows)]);

        if ($parameters['include_unvalidated'] ?? false) {
            fputcsv($handle, ['WARNING', 'Includes records that have NOT been validated.']);
        } else {
            fputcsv($handle, ['Data', 'Validated (approved) records only']);
        }

        fputcsv($handle, []);
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
