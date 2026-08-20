<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KpiAssignment;
use App\Models\KpiContribution;
use App\Models\KpiMeasure;
use App\Models\KpiPeriod;
use App\Models\KpiProgress;
use App\Models\KpiTarget;
use App\Models\StaffProfile;
use App\Services\Audit\AuditLogger;
use App\Services\Kpi\KpiProgressCalculator;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KpiController extends Controller
{
    public function __construct(
        private readonly KpiProgressCalculator $calculator,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function periods(): JsonResponse
    {
        return response()->json([
            'data' => KpiPeriod::orderBy('start_date')->get(['id', 'code', 'label', 'start_date', 'end_date', 'is_locked']),
        ]);
    }

    public function measures(): JsonResponse
    {
        return response()->json([
            'data' => KpiMeasure::where('is_active', true)
                ->get(['id', 'code', 'label', 'source_kind', 'aggregation', 'unit', 'research_type_id']),
        ]);
    }

    /** Targets visible to this user, scoped by the policy's own rules. */
    public function targets(Request $request): JsonResponse
    {
        $this->authorize('viewAny', KpiTarget::class);

        $user = $request->user();

        $targets = KpiTarget::query()
            ->with(['period:id,code,label', 'measure:id,code,label,unit', 'criteria', 'progress'])
            ->when($periodId = $request->integer('period_id'), fn ($q) => $q->where('kpi_period_id', $periodId))
            ->get()
            // Filter through the policy so one rule governs both list and read.
            ->filter(fn (KpiTarget $t) => $user->can('view', $t))
            ->values();

        return response()->json(['data' => $targets]);
    }

    public function storeTarget(Request $request): JsonResponse
    {
        $this->authorize('create', KpiTarget::class);

        $validated = $request->validate([
            'kpi_period_id'      => ['required', 'integer', 'exists:kpi_periods,id'],
            'kpi_measure_id'     => ['required', 'integer', 'exists:kpi_measures,id'],
            'scope_type'         => ['required', Rule::in(['INSTITUTION', 'FACULTY', 'STAFF'])],
            'scope_id'           => ['nullable', 'integer'],
            'target_value'       => ['required', 'numeric', 'gt:0'],
            'description'        => ['nullable', 'string', 'max:255'],
            'criteria'           => ['array'],
            'criteria.*.field_path' => ['required_with:criteria', 'string', 'max:100'],
            'criteria.*.operator'   => ['required_with:criteria', Rule::in(['=', '!=', 'in', '>=', '<=', '>', '<', 'contains'])],
            'criteria.*.value'      => ['required_with:criteria', 'string', 'max:255'],
        ]);

        // Institution-wide targets belong to Admin; a TDPP may set targets only
        // within a faculty they currently serve.
        $user = $request->user();

        if (! $user->isAdmin()) {
            if ($validated['scope_type'] === 'INSTITUTION') {
                abort(403, 'Only an administrator may set institution-wide targets.');
            }

            $facultyId = $validated['scope_type'] === 'FACULTY'
                ? (int) $validated['scope_id']
                : StaffProfile::find($validated['scope_id'])?->currentFacultyId();

            if ($facultyId === null || ! $user->canValidateForFaculty($facultyId)) {
                abort(403, 'That target is outside the faculty you serve.');
            }
        }

        $target = DB::transaction(function () use ($validated, $request) {
            $target = KpiTarget::create([
                'kpi_period_id'  => $validated['kpi_period_id'],
                'kpi_measure_id' => $validated['kpi_measure_id'],
                'scope_type'     => $validated['scope_type'],
                'scope_id'       => $validated['scope_type'] === 'INSTITUTION' ? null : $validated['scope_id'],
                'target_value'   => $validated['target_value'],
                'description'    => $validated['description'] ?? null,
                'created_by'     => $request->user()->id,
            ]);

            foreach ($validated['criteria'] ?? [] as $criterion) {
                $target->criteria()->create($criterion);
            }

            return $target;
        });

        $this->audit->log(AuditLogger::KPI_TARGET_SET, $target, null, $validated);
        $this->calculator->recomputeTarget($target->fresh(['criteria', 'measure', 'period']));

        return response()->json(['data' => $target->load(['criteria', 'progress'])], 201);
    }

    /** Assign a staff-scope target to a lecturer. */
    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kpi_target_id'    => ['required', 'integer', 'exists:kpi_targets,id'],
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'deadline'         => ['nullable', 'date', 'after:today'],
            'note'             => ['nullable', 'string', 'max:1000'],
        ]);

        $target = KpiTarget::findOrFail($validated['kpi_target_id']);
        $this->authorize('update', $target);

        $staff = StaffProfile::findOrFail($validated['staff_profile_id']);
        $user  = $request->user();

        // A TDPP may only assign within the faculty they serve.
        if (! $user->isAdmin()) {
            $facultyId = $staff->currentFacultyId();

            if ($facultyId === null || ! $user->canValidateForFaculty($facultyId)) {
                abort(403, 'That lecturer is not in the faculty you serve.');
            }
        }

        $assignment = KpiAssignment::updateOrCreate(
            ['kpi_target_id' => $target->id, 'staff_profile_id' => $staff->id],
            [
                'assigned_by_staff_profile_id' => $user->staffProfile?->id,
                'assigned_at' => now(),
                'deadline'    => $validated['deadline'] ?? null,
                'status'      => 'OPEN',
                'note'        => $validated['note'] ?? null,
            ],
        );

        $this->audit->log(AuditLogger::KPI_ASSIGNED, $assignment, null, $validated);

        if ($staff->user_id) {
            $this->notifications->kpiAssigned($staff->user_id, [
                'assignment_id' => $assignment->id,
                'measure'       => $target->measure->label,
                'target_value'  => (float) $target->target_value,
                'period'        => $target->period->code,
                'deadline'      => $assignment->deadline?->toDateString(),
            ]);
        }

        // Existing approved work in the period counts immediately — D4 credits
        // by effective date, so back-dated output is not lost.
        $this->calculator->recomputeTarget($target->fresh(['criteria', 'measure', 'period', 'assignments']));

        return response()->json(['data' => $assignment->fresh(['progress'])], 201);
    }

    public function unassign(KpiAssignment $assignment, Request $request): JsonResponse
    {
        $this->authorize('update', $assignment->target);

        $this->audit->log(AuditLogger::KPI_UNASSIGNED, $assignment);
        $assignment->delete();

        return response()->json(['data' => ['message' => 'Assignment removed.']]);
    }

    /** My assignments, or a lecturer's if the caller may see them. */
    public function assignments(Request $request): JsonResponse
    {
        $user    = $request->user();
        $staffId = $request->integer('staff_profile_id') ?: $user->staffProfile?->id;

        if ($staffId !== $user->staffProfile?->id) {
            $staff     = StaffProfile::findOrFail($staffId);
            $facultyId = $staff->currentFacultyId();

            if (! $user->isAdmin() && ! ($facultyId && $user->canValidateForFaculty($facultyId))) {
                abort(403, 'You may not view that lecturer’s KPI.');
            }
        }

        $assignments = KpiAssignment::query()
            ->with(['target.measure:id,code,label,unit', 'target.period:id,code,label', 'progress'])
            ->where('staff_profile_id', $staffId)
            ->get();

        return response()->json(['data' => $assignments]);
    }

    /**
     * Exactly which records credited an assignment.
     *
     * This is the difference between a progress number and an auditable one —
     * ARAMS 1.0 showed a bare counter that reached 19 against a target of 1
     * with no way to see what it had counted.
     */
    public function contributions(KpiAssignment $assignment, Request $request): JsonResponse
    {
        $user  = $request->user();
        $staff = $assignment->staffProfile;

        if ($staff?->id !== $user->staffProfile?->id) {
            $facultyId = $staff?->currentFacultyId();

            if (! $user->isAdmin() && ! ($facultyId && $user->canValidateForFaculty($facultyId))) {
                abort(403, 'You may not view those contributions.');
            }
        }

        $contributions = KpiContribution::query()
            ->with('researchRecord:id,display_title,effective_date,research_type_id')
            ->where('kpi_assignment_id', $assignment->id)
            ->orderBy('counted_on')
            ->get()
            ->map(fn (KpiContribution $c) => [
                'research_record_id' => $c->research_record_id,
                'title'              => $c->researchRecord?->display_title,
                'counted_on'         => $c->counted_on?->toDateString(),
                'contributed_value'  => (float) $c->contributed_value,
            ]);

        return response()->json([
            'data' => [
                'assignment_id' => $assignment->id,
                'progress'      => KpiProgress::where('kpi_assignment_id', $assignment->id)->first(),
                'contributions' => $contributions,
            ],
        ]);
    }
}
