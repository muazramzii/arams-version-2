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
        $this->calculator->backfillTarget($target->fresh(['criteria', 'measure', 'period']));

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
        $this->calculator->backfillTarget($target->fresh(['criteria', 'measure', 'period', 'assignments']));

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
     * Researchers this user may assign KPI to, with their current progress.
     *
     * Scoped from the appointment, not from a request parameter: a TDPP sees
     * their own faculty and nobody else's. Admin sees everyone.
     */
    public function assignableStaff(Request $request): JsonResponse
    {
        $user = $request->user();

        $facultyIds = $user->isAdmin()
            ? null
            : ($user->staffProfile?->currentAppointments()->pluck('faculty_id')->all() ?? []);

        if ($facultyIds !== null && $facultyIds === []) {
            return response()->json(['data' => []]);
        }

        $periodId = $request->integer('period_id') ?: null;

        $staff = StaffProfile::query()
            ->whereHas('user', fn ($q) => $q->where('role', 'Lecturer')->where('is_active', true))
            ->when($facultyIds !== null, fn ($q) => $q->whereHas(
                'affiliations',
                fn ($a) => $a->whereIn('faculty_id', $facultyIds)->whereNull('valid_to'),
            ))
            ->orderBy('full_name')
            ->get(['id', 'user_id', 'full_name', 'staff_no', 'is_archived']);

        $assignments = KpiAssignment::query()
            ->whereIn('staff_profile_id', $staff->pluck('id'))
            ->when($periodId, fn ($q) => $q->whereHas(
                'target',
                fn ($t) => $t->where('kpi_period_id', $periodId),
            ))
            ->with(['target.measure:id,code,label', 'progress'])
            ->get()
            ->groupBy('staff_profile_id');

        return response()->json([
            'data' => $staff->map(fn (StaffProfile $person) => [
                'id'          => $person->id,
                'full_name'   => $person->full_name,
                'staff_no'    => $person->staff_no,
                'is_archived' => (bool) $person->is_archived,
                'assignments' => ($assignments[$person->id] ?? collect())
                    ->map(fn (KpiAssignment $assignment) => [
                        'id'       => $assignment->id,
                        'measure'  => $assignment->target?->measure?->label,
                        'target'   => (float) ($assignment->target?->target_value ?? 0),
                        'achieved' => (float) ($assignment->progress->first()?->achieved_value ?? 0),
                        'status'   => $assignment->status->value,
                        'deadline' => $assignment->deadline?->toDateString(),
                    ])->values(),
            ]),
        ]);
    }

    /**
     * Create a staff-scope target and assign it in one step.
     *
     * Assigning in ARAMS 1.0 meant filling four criteria columns on a task
     * row. Here the target and its criteria are proper records, so the same
     * target can be reused and the criteria are evaluated by one engine.
     */
    public function assignToStaff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'kpi_period_id'    => ['required', 'integer', 'exists:kpi_periods,id'],
            'kpi_measure_id'   => ['required', 'integer', 'exists:kpi_measures,id'],
            'target_value'     => ['required', 'numeric', 'gt:0'],
            'deadline'         => ['nullable', 'date', 'after:today'],
            'description'      => ['nullable', 'string', 'max:255'],
            'quartile'         => ['nullable', Rule::in(['Q1', 'Q2', 'Q3', 'Q4'])],
            'indexing_code'    => ['nullable', 'string', 'max:40'],
        ]);

        $user  = $request->user();
        $staff = StaffProfile::findOrFail($validated['staff_profile_id']);

        if (! $user->isAdmin()) {
            $facultyId = $staff->currentFacultyId();

            if ($facultyId === null || ! $user->canValidateForFaculty($facultyId)) {
                abort(403, 'That lecturer is not in the faculty you serve.');
            }
        }

        $assignment = DB::transaction(function () use ($validated, $staff, $user) {
            // A variant keeps two targets on one measure and period apart —
            // "1 Q1 paper" and "3 any papers" are different asks.
            $variant = 'STAFF-' . $staff->id . '-' . strtoupper(
                ($validated['quartile'] ?? '') . ($validated['indexing_code'] ?? '') ?: 'ANY'
            );

            $target = KpiTarget::firstOrCreate(
                [
                    'kpi_period_id'  => $validated['kpi_period_id'],
                    'kpi_measure_id' => $validated['kpi_measure_id'],
                    'scope_type'     => 'STAFF',
                    'scope_id'       => $staff->id,
                    'variant_code'   => $variant,
                ],
                [
                    'target_value' => $validated['target_value'],
                    'description'  => $validated['description'] ?? null,
                    'created_by'   => $user->id,
                ],
            );

            $target->update(['target_value' => $validated['target_value']]);

            $target->criteria()->delete();

            if (! empty($validated['quartile'])) {
                $target->criteria()->create([
                    'field_path' => 'quartile', 'operator' => '=', 'value' => $validated['quartile'],
                ]);
            }

            if (! empty($validated['indexing_code'])) {
                // `contains`, never `=` — a paper indexed in Scopus and WoS
                // must satisfy a Scopus criterion. ARAMS 1.0 used equality
                // against a SET column and missed exactly those papers.
                $target->criteria()->create([
                    'field_path' => 'indexings',
                    'operator'   => 'contains',
                    'value'      => strtoupper($validated['indexing_code']),
                ]);
            }

            return KpiAssignment::updateOrCreate(
                ['kpi_target_id' => $target->id, 'staff_profile_id' => $staff->id],
                [
                    'assigned_by_staff_profile_id' => $user->staffProfile?->id,
                    'assigned_at' => now(),
                    'deadline'    => $validated['deadline'] ?? null,
                    'status'      => 'OPEN',
                ],
            );
        });

        $this->audit->log(AuditLogger::KPI_ASSIGNED, $assignment, null, $validated);

        if ($staff->user_id) {
            $this->notifications->kpiAssigned($staff->user_id, [
                'assignment_id' => $assignment->id,
                'measure'       => $assignment->target->measure->label,
                'target_value'  => (float) $assignment->target->target_value,
                'period'        => $assignment->target->period->code,
                'deadline'      => $assignment->deadline?->toDateString(),
            ]);
        }

        // Approved work already inside the period counts at once — D4 credits
        // by effective date, so back-dated output is not lost on assignment.
        $this->calculator->backfillTarget(
            $assignment->target->fresh(['criteria', 'measure', 'period', 'assignments'])
        );

        return response()->json([
            'data' => $assignment->fresh(['progress', 'target.measure']),
        ], 201);
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
