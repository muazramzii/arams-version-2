<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\FacultyLeader;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Institution-level administration.
 *
 * The appointment endpoints exist because D1 removed the Admin validation
 * fallback: if a faculty has no serving TDPP, nobody can validate there, and
 * the only remedy is an appointment. FKAAS is in exactly that state with 77
 * lecturers, so this cannot be a database-only operation.
 */
class AdminController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {}

    /** Faculties with their current validator coverage. */
    public function faculties(): JsonResponse
    {
        $faculties = Faculty::query()
            ->with(['currentLeaders.staffProfile:id,full_name,staff_no'])
            ->withCount([
                'affiliations as staff_count' => fn ($q) => $q->whereNull('valid_to'),
            ])
            ->orderBy('code')
            ->get()
            ->map(fn (Faculty $faculty) => [
                'id'          => $faculty->id,
                'code'        => $faculty->code,
                'name'        => $faculty->name,
                'staff_count' => $faculty->staff_count,
                'leaders'     => $faculty->currentLeaders->map(fn (FacultyLeader $leader) => [
                    'id'         => $leader->id,
                    'staff_id'   => $leader->staff_profile_id,
                    'name'       => $leader->staffProfile?->full_name,
                    'valid_from' => $leader->valid_from?->toDateString(),
                ])->values(),
                // The operational flag: staff but nobody who can validate.
                'needs_tdpp'  => $faculty->currentLeaders->isEmpty() && $faculty->staff_count > 0,
            ]);

        return response()->json(['data' => $faculties]);
    }

    /**
     * Appoint a TDPP.
     *
     * The appointee must already hold the TDPP role — this grants the faculty
     * scope, not the role itself, so the two decisions stay separate and both
     * are audited.
     */
    public function appointLeader(Faculty $faculty, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'valid_from'       => ['nullable', 'date'],
            'note'             => ['nullable', 'string', 'max:255'],
        ]);

        $staff = StaffProfile::with('user')->findOrFail($validated['staff_profile_id']);

        if ($staff->user?->role->value !== 'TDPP') {
            return response()->json([
                'title'  => 'Action not allowed',
                'status' => 422,
                'detail' => 'Only a user with the TDPP role can be appointed. Change their role first.',
            ], 422);
        }

        $alreadyServing = FacultyLeader::where('faculty_id', $faculty->id)
            ->where('staff_profile_id', $staff->id)
            ->whereNull('valid_to')
            ->exists();

        if ($alreadyServing) {
            return response()->json([
                'title'  => 'Action not allowed',
                'status' => 422,
                'detail' => 'That person already holds a current appointment for this faculty.',
            ], 422);
        }

        $leader = FacultyLeader::create([
            'faculty_id'       => $faculty->id,
            'staff_profile_id' => $staff->id,
            'appointment'      => 'TDPP',
            'valid_from'       => $validated['valid_from'] ?? now()->toDateString(),
            'appointed_by'     => $request->user()->id,
            'note'             => $validated['note'] ?? null,
        ]);

        $this->audit->log(AuditLogger::APPOINTMENT_CREATED, $leader, null, [
            'faculty' => $faculty->code,
            'staff'   => $staff->full_name,
        ]);

        return response()->json(['data' => [
            'id'         => $leader->id,
            'faculty'    => $faculty->code,
            'name'       => $staff->full_name,
            'valid_from' => $leader->valid_from->toDateString(),
        ]], 201);
    }

    /**
     * End an appointment. Never deleted — an outgoing TDPP's past decisions
     * have to stay explicable, which is the point of dating appointments.
     */
    public function endLeader(FacultyLeader $facultyLeader, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'valid_to' => ['nullable', 'date', 'after_or_equal:' . $facultyLeader->valid_from->toDateString()],
        ]);

        if ($facultyLeader->valid_to !== null) {
            return response()->json([
                'title'  => 'Action not allowed',
                'status' => 422,
                'detail' => 'That appointment has already ended.',
            ], 422);
        }

        $facultyLeader->update(['valid_to' => $validated['valid_to'] ?? now()->toDateString()]);

        $this->audit->log(AuditLogger::APPOINTMENT_ENDED, $facultyLeader);

        // Ending the last appointment leaves the faculty unable to validate.
        $remaining = FacultyLeader::where('faculty_id', $facultyLeader->faculty_id)
            ->whereNull('valid_to')
            ->count();

        if ($remaining === 0) {
            $faculty = Faculty::find($facultyLeader->faculty_id);
            $this->notifications->noValidator($faculty->id, $faculty->code);
        }

        return response()->json(['data' => [
            'id'                 => $facultyLeader->id,
            'valid_to'           => $facultyLeader->valid_to->toDateString(),
            'faculty_now_uncovered' => $remaining === 0,
        ]]);
    }

    /** Users directory. */
    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('staffProfile:id,user_id,full_name,staff_no,is_archived')
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('email', 'like', "%{$term}%")
                ->orWhereHas('staffProfile', fn ($s) => $s->where('full_name', 'like', "%{$term}%"))))
            ->orderBy('email')
            ->limit($request->integer('limit', 100))
            ->get()
            ->map(fn (User $user) => [
                'id'          => $user->id,
                'email'       => $user->email,
                'role'        => $user->role->value,
                'is_active'   => $user->is_active,
                'staff_id'    => $user->staffProfile?->id,
                'full_name'   => $user->staffProfile?->full_name,
                'staff_no'    => $user->staffProfile?->staff_no,
                'is_archived' => (bool) $user->staffProfile?->is_archived,
                'last_login'  => $user->last_login_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $users]);
    }

    /** Activate or deactivate an account. */
    public function setUserActivation(User $user, Request $request): JsonResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'title'  => 'Action not allowed',
                'status' => 422,
                'detail' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $before = $user->is_active;
        $user->update(['is_active' => $validated['is_active']]);

        // Deactivating must not leave a live session usable.
        if (! $validated['is_active']) {
            $user->tokens()->delete();
        }

        $this->audit->logChange(
            $validated['is_active'] ? AuditLogger::USER_ACTIVATED : AuditLogger::USER_DEACTIVATED,
            $user,
            ['is_active' => $before],
            ['is_active' => $validated['is_active']],
        );

        return response()->json(['data' => ['id' => $user->id, 'is_active' => $user->is_active]]);
    }

    public function setUserRole(User $user, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['Lecturer', 'TDPP', 'Admin'])],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'title'  => 'Action not allowed',
                'status' => 422,
                'detail' => 'You cannot change your own role.',
            ], 422);
        }

        $before = $user->role->value;

        // Dropping the TDPP role has to end the appointments it justified,
        // or the faculty keeps a validator who can no longer validate.
        if ($before === 'TDPP' && $validated['role'] !== 'TDPP' && $user->staffProfile) {
            FacultyLeader::where('staff_profile_id', $user->staffProfile->id)
                ->whereNull('valid_to')
                ->update(['valid_to' => now()->toDateString(), 'updated_at' => now()]);
        }

        $user->update(['role' => $validated['role']]);
        $user->tokens()->delete();

        $this->audit->logChange('user.role_changed', $user, ['role' => $before], ['role' => $validated['role']]);

        return response()->json(['data' => ['id' => $user->id, 'role' => $user->role->value]]);
    }

    /** TDPP-role users, for the appointment picker. */
    public function appointableStaff(): JsonResponse
    {
        $staff = StaffProfile::query()
            ->whereHas('user', fn ($q) => $q->where('role', 'TDPP')->where('is_active', true))
            ->with('user:id,email')
            ->orderBy('full_name')
            ->get(['id', 'user_id', 'full_name', 'staff_no'])
            ->map(fn (StaffProfile $staff) => [
                'id'        => $staff->id,
                'full_name' => $staff->full_name,
                'staff_no'  => $staff->staff_no,
                'email'     => $staff->user?->email,
                'serving'   => $staff->currentAppointments()->pluck('faculty_id'),
            ]);

        return response()->json(['data' => $staff]);
    }

    /** Records still needing an effective date — the backfill worklist. */
    public function dataQuality(): JsonResponse
    {
        $missingDates = DB::table('research_records')
            ->join('research_types', 'research_types.id', '=', 'research_records.research_type_id')
            ->whereNull('research_records.deleted_at')
            ->where('research_records.effective_date_precision', 'UNKNOWN')
            ->groupBy('research_types.label')
            ->pluck(DB::raw('COUNT(*)'), 'research_types.label')
            ->all();

        return response()->json(['data' => [
            'records_missing_effective_date' => $missingDates,
            'total_missing'                  => array_sum($missingDates),
            'approvals_without_approver'     => DB::table('submissions')
                ->where('status', 'APPROVED')->whereNull('decided_by')->count(),
            'archived_staff'                 => DB::table('staff_profiles')
                ->where('is_archived', true)->count(),
        ]]);
    }
}
