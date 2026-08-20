<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubmissionResource;
use App\Models\Submission;
use App\Services\Organisation\AffiliationResolver;
use App\Services\Submission\SubmissionService;
use App\Services\Submission\SubmissionStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionService $submissions,
        private readonly SubmissionStateMachine $stateMachine,
        private readonly AffiliationResolver $affiliations,
    ) {}

    /**
     * Scope is derived from the authenticated user, never from a request
     * parameter. A client-supplied faculty_id can only narrow what the user is
     * already entitled to see — it can never widen it.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Submission::class);

        $user  = $request->user();
        $query = Submission::query()->with(['researchRecord.researchType', 'researchRecord.owner']);

        if ($user->isLecturer()) {
            $query->where('submitted_by', $user->id);
        } elseif ($user->isTdpp()) {
            $facultyIds = $user->staffProfile?->currentAppointments()->pluck('faculty_id') ?? collect();
            $query->whereIn('faculty_id_at_submission', $facultyIds->all() ?: [0]);
        }
        // Admin sees everything, read-only as far as validation is concerned.

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($facultyId = $request->integer('faculty_id')) {
            $query->where('faculty_id_at_submission', $facultyId);
        }

        return SubmissionResource::collection(
            $query->latest('submitted_at')->cursorPaginate($request->integer('per_page', 25))
        );
    }

    /** The TDPP validation queue for the faculties this user serves. */
    public function queue(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Submission::class);

        $user       = $request->user();
        $facultyIds = $user->staffProfile?->currentAppointments()->pluck('faculty_id')->all() ?? [];

        $query = Submission::query()
            ->with(['researchRecord.researchType', 'researchRecord.owner'])
            ->pendingReview();

        // Admin may observe any queue; a TDPP is confined to their own.
        $query->when(
            ! $user->isAdmin(),
            fn ($q) => $q->whereIn('faculty_id_at_submission', $facultyIds ?: [0]),
        );

        return SubmissionResource::collection(
            $query->orderBy('submitted_at')->cursorPaginate($request->integer('per_page', 25))
        );
    }

    public function show(Submission $submission): SubmissionResource
    {
        $this->authorize('view', $submission);

        return new SubmissionResource(
            $submission->load(['researchRecord.researchType', 'researchRecord.owner', 'reviews.reviewer', 'revisions'])
        );
    }

    public function submit(Submission $submission, Request $request): SubmissionResource
    {
        $this->authorize('submit', $submission);

        return new SubmissionResource(
            $this->submissions->submit($submission, $request->user())
        );
    }

    public function withdraw(Submission $submission, Request $request): SubmissionResource
    {
        $this->authorize('withdraw', $submission);

        return new SubmissionResource(
            $this->submissions->withdraw($submission, $request->user())
        );
    }

    public function claim(Submission $submission, Request $request): SubmissionResource
    {
        $this->authorize('claim', $submission);

        return new SubmissionResource(
            $this->submissions->claim($submission, $request->user())
        );
    }

    public function approve(Submission $submission, Request $request): SubmissionResource
    {
        $this->authorize('decide', $submission);

        $validated = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        return new SubmissionResource($this->submissions->decide(
            $submission, $request->user(), SubmissionStatus::Approved, $validated['remarks'] ?? null,
        ));
    }

    public function reject(Submission $submission, Request $request): SubmissionResource
    {
        $this->authorize('decide', $submission);

        // Remarks are mandatory: a rejection the lecturer cannot act on is
        // the dead end that produced duplicate records in ARAMS 1.0.
        $validated = $request->validate(['remarks' => ['required', 'string', 'min:3', 'max:2000']]);

        return new SubmissionResource($this->submissions->decide(
            $submission, $request->user(), SubmissionStatus::Rejected, $validated['remarks'],
        ));
    }

    public function requestRevision(Submission $submission, Request $request): SubmissionResource
    {
        $this->authorize('decide', $submission);

        $validated = $request->validate(['remarks' => ['required', 'string', 'min:3', 'max:2000']]);

        return new SubmissionResource($this->submissions->decide(
            $submission, $request->user(), SubmissionStatus::RevisionRequested, $validated['remarks'],
        ));
    }

    /** Which moves this user may make right now — drives the UI's buttons. */
    public function transitions(Submission $submission, Request $request): JsonResponse
    {
        $this->authorize('view', $submission);

        return response()->json([
            'data' => $this->stateMachine->availableTo($request->user(), $submission),
        ]);
    }

    /**
     * Faculties with no serving TDPP. Under D1 nobody can validate there, so
     * this is an operational alert rather than a statistic — FKAAS has 77
     * lecturers and no appointment.
     */
    public function coverageGaps(): JsonResponse
    {
        $this->authorize('viewAny', Submission::class);

        return response()->json([
            'data' => $this->affiliations->facultiesWithoutValidator()
                ->map(fn ($f) => [
                    'faculty_id' => $f->id,
                    'code'       => $f->code,
                    'name'       => $f->name,
                ])
                ->values(),
        ]);
    }
}
