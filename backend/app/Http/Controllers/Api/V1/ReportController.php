<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Services\Analytics\AnalyticsScope;
use App\Services\Audit\AuditLogger;
use App\Services\Reporting\ReportRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const ROLE_RANK = ['Lecturer' => 1, 'TDPP' => 2, 'Admin' => 3];

    public function __construct(
        private readonly ReportRunner $runner,
        private readonly AuditLogger $audit,
    ) {}

    /** Only the definitions this role may actually run. */
    public function definitions(Request $request): JsonResponse
    {
        $rank = self::ROLE_RANK[$request->user()->role->value] ?? 0;

        $definitions = ReportDefinition::where('is_active', true)
            ->get()
            ->filter(fn ($d) => (self::ROLE_RANK[$d->minimum_role] ?? 3) <= $rank)
            ->values()
            ->map(fn ($d) => [
                'code'              => $d->code,
                'title'             => $d->title,
                'description'       => $d->description,
                'parameter_schema'  => $d->parameter_schema,
                'supported_formats' => ReportRunner::SUPPORTED_FORMATS,
            ]);

        return response()->json(['data' => $definitions]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ReportRun::class);

        $validated = $request->validate([
            'code'   => ['required', Rule::exists('report_definitions', 'code')->where('is_active', true)],
            'format' => ['required', Rule::in(['CSV', 'PDF', 'XLSX'])],
            'parameters'                        => ['array'],
            'parameters.period_id'              => ['nullable', 'integer', 'exists:kpi_periods,id'],
            'parameters.faculty_id'             => ['nullable', 'integer', 'exists:faculties,id'],
            'parameters.include_unvalidated'    => ['boolean'],
        ]);

        $definition = ReportDefinition::where('code', $validated['code'])->firstOrFail();

        $rank = self::ROLE_RANK[$request->user()->role->value] ?? 0;
        abort_if(
            (self::ROLE_RANK[$definition->minimum_role] ?? 3) > $rank,
            403,
            'Your role may not run that report.',
        );

        // Scope is fixed at generation and baked into the artifact, so a
        // faculty report cannot later be widened by replaying its parameters.
        $scope = AnalyticsScope::for($request->user());

        $run = $this->runner->run(
            $definition,
            $request->user(),
            $scope,
            $validated['parameters'] ?? [],
            $validated['format'],
        );

        return response()->json(['data' => $this->present($run)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ReportRun::class);

        $runs = ReportRun::query()
            ->with('definition:id,code,title')
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('requested_by', $request->user()->id))
            ->latest()
            ->limit($request->integer('limit', 50))
            ->get()
            ->map(fn (ReportRun $r) => $this->present($r));

        return response()->json(['data' => $runs]);
    }

    public function download(ReportRun $reportRun, Request $request): StreamedResponse
    {
        $this->authorize('download', $reportRun);

        abort_if($reportRun->status !== 'READY', 409, 'That report is not ready.');
        abort_if(
            $reportRun->expires_at && $reportRun->expires_at->isPast(),
            410,
            'That report has expired. Generate it again.',
        );
        abort_unless(
            $reportRun->file_path && Storage::disk('local')->exists($reportRun->file_path),
            404,
            'The report file is no longer available.',
        );

        $this->audit->log(AuditLogger::REPORT_DOWNLOADED, $reportRun);

        return Storage::disk('local')->download(
            $reportRun->file_path,
            basename($reportRun->file_path),
        );
    }

    private function present(ReportRun $run): array
    {
        return [
            'id'           => $run->id,
            'definition'   => $run->definition?->code,
            'title'        => $run->definition?->title,
            'format'       => $run->format,
            'status'       => $run->status,
            'row_count'    => $run->row_count,
            'scope_type'   => $run->scope_type?->value,
            'parameters'   => $run->parameters,
            // Lets a printed copy be matched back to the exact artifact.
            'file_hash'    => $run->file_hash,
            'generated_at' => $run->generated_at?->toIso8601String(),
            'expires_at'   => $run->expires_at?->toIso8601String(),
        ];
    }
}
