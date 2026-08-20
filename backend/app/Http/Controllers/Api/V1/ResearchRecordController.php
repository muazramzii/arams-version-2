<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResearchRecordResource;
use App\Models\ResearchRecord;
use App\Models\ResearchType;
use App\Services\Audit\AuditLogger;
use App\Services\Kpi\KpiProgressCalculator;
use App\Services\Research\ResearchRecordWriter;
use App\Services\Submission\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ResearchRecordController extends Controller
{
    public function __construct(
        private readonly ResearchRecordWriter $writer,
        private readonly SubmissionService $submissions,
        private readonly KpiProgressCalculator $kpi,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ResearchRecord::class);

        $user  = $request->user();
        $query = ResearchRecord::query()->with(['researchType', 'owner', 'submission']);

        // Scope from the session, never from a parameter.
        if ($user->isLecturer()) {
            $query->where('owner_staff_profile_id', $user->staffProfile?->id ?? 0);
        } elseif ($user->isTdpp()) {
            $facultyIds = $user->staffProfile?->currentAppointments()->pluck('faculty_id')->all() ?? [];
            $query->whereIn('attributed_faculty_id', $facultyIds ?: [0]);
        }

        if ($type = $request->query('type')) {
            $query->whereHas('researchType', fn ($q) => $q->where('code', $type));
        }

        if ($request->boolean('needs_date_backfill')) {
            $query->where('effective_date_precision', 'UNKNOWN');
        }

        if ($request->boolean('approved_only')) {
            $query->countable();
        }

        return ResearchRecordResource::collection(
            $query->latest('effective_date')->cursorPaginate($request->integer('per_page', 25))
        );
    }

    public function show(ResearchRecord $researchRecord): ResearchRecordResource
    {
        $this->authorize('view', $researchRecord);

        return new ResearchRecordResource(
            $researchRecord->load(['researchType', 'owner', 'submission.reviews.reviewer'])
        );
    }

    /**
     * Create a record as a DRAFT with its workflow row.
     *
     * Nothing is auto-approved. ARAMS 1.0's api/admin_add_record.php inserted
     * rows pre-stamped 'Approved', which bypassed validation entirely — under
     * D1 that path does not exist, and an Admin acting for a lecturer creates
     * a draft the lecturer must submit themselves.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ResearchRecord::class);

        $request->validate([
            'type' => ['required', Rule::exists('research_types', 'code')->where('is_active', true)],
        ]);

        $type = ResearchType::where('code', $request->string('type'))->firstOrFail();
        $data = $request->validate(ResearchRecordWriter::rulesFor($type->code));

        $owner = $request->user()->staffProfile;

        $record = $this->writer->create($type, $owner, $data);
        $this->submissions->createDraft($record, $request->user());

        return (new ResearchRecordResource($record->load(['researchType', 'owner', 'submission'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ResearchRecord $researchRecord, Request $request): ResearchRecordResource
    {
        // Policy allows this only while DRAFT or REVISION_REQUESTED.
        $this->authorize('update', $researchRecord);

        $data = $request->validate(
            ResearchRecordWriter::rulesFor($researchRecord->researchType->code)
        );

        return new ResearchRecordResource(
            $this->writer->update($researchRecord, $data)->load(['researchType', 'owner', 'submission'])
        );
    }

    /**
     * Soft delete. Deletion and workflow status are independent axes: an
     * approved record stays APPROVED and becomes deleted, because the approval
     * is a historical fact. Its KPI credit is withdrawn and progress recomputed.
     */
    public function destroy(ResearchRecord $researchRecord, Request $request): JsonResponse
    {
        $this->authorize('delete', $researchRecord);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $researchRecord->update([
            'deleted_by'      => $request->user()->id,
            'deletion_reason' => $validated['reason'],
        ]);
        $researchRecord->delete();

        $this->kpi->recomputeForRecord($researchRecord);
        $this->audit->log(AuditLogger::RECORD_DELETED, $researchRecord, null, $validated);

        return response()->json(['data' => ['message' => 'Record deleted.']]);
    }

    public function restore(int $id, Request $request): ResearchRecordResource
    {
        $record = ResearchRecord::withTrashed()->findOrFail($id);
        $this->authorize('restore', $record);

        $record->restore();
        $record->update(['deleted_by' => null, 'deletion_reason' => null]);

        $this->kpi->recomputeForRecord($record);
        $this->audit->log(AuditLogger::RECORD_RESTORED, $record);

        return new ResearchRecordResource($record->load(['researchType', 'owner', 'submission']));
    }
}
