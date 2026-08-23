<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GrantProject;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

/**
 * Grant projects — the institutional object, shared between participants.
 *
 * This is the endpoint that makes the Phase 2 project/participation split work
 * in practice. A lecturer joining a grant searches for its code first; if a
 * colleague already registered it, they attach to the existing project rather
 * than creating a second copy of it.
 *
 * ARAMS 1.0 had no such step, which is how eleven grant codes ended up
 * duplicated and RM 420,000 of funding was counted more than once.
 */
class GrantProjectController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Search by code or title, for the picker. */
    public function index(Request $request): JsonResponse
    {
        $projects = GrantProject::query()
            ->with([
                'level:id,label',
                'funder:id,label',
                'participations.owner:id,full_name',
            ])
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('grant_code', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")))
            ->orderBy('grant_code')
            ->limit($request->integer('limit', 25))
            ->get()
            ->map(fn (GrantProject $project) => [
                'id'            => $project->id,
                'grant_code'    => $project->grant_code,
                'title'         => $project->title,
                'level'         => $project->level?->label,
                'funder'        => $project->funder?->label,
                'total_amount'  => $project->total_amount,
                'start_date'    => $project->start_date?->toDateString(),
                'end_date'      => $project->end_date?->toDateString(),
                // Shown in the picker so a lecturer can see they are joining
                // an existing grant rather than starting a duplicate.
                'participants'  => $project->participations
                    ->map(fn ($p) => $p->owner?->full_name)
                    ->filter()
                    ->values(),
                'needs_start_date' => $project->start_date === null,
            ]);

        return response()->json(['data' => $projects]);
    }

    /**
     * Register a grant project that is not in ARAMS yet.
     *
     * Creating the project is not the same as claiming participation in it —
     * the claim is a separate research record, so the shared object and the
     * personal credit stay distinct.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grant_code'        => ['required', 'string', 'max:100', 'unique:grant_projects,grant_code'],
            'title'             => ['required', 'string', 'max:500'],
            'funder_id'         => ['nullable', 'integer', 'exists:funders,id'],
            'grant_level_id'    => ['nullable', 'integer', 'exists:grant_levels,id'],
            'grant_category_id' => ['nullable', 'integer', 'exists:grant_categories,id'],
            'grant_status_id'   => ['nullable', 'integer', 'exists:grant_statuses,id'],
            'total_amount'      => ['nullable', 'numeric', 'min:0'],
            // Nullable, but the UI warns that omitting it keeps the grant out
            // of period-scoped KPI — the state 70 of 71 migrated grants are in.
            'start_date'        => ['nullable', 'date'],
            'end_date'          => ['nullable', 'date', 'after_or_equal:start_date'],
            'mygrants_id'       => ['nullable', 'string', 'max:50'],
        ], [
            'grant_code.unique' => 'That grant code is already registered. Search for it instead of creating a second copy.',
        ]);

        $project = GrantProject::create($validated + ['currency' => 'MYR']);

        $this->audit->log('grant_project.created', $project, null, [
            'grant_code' => $project->grant_code,
        ]);

        return response()->json(['data' => [
            'id'         => $project->id,
            'grant_code' => $project->grant_code,
            'title'      => $project->title,
            'needs_start_date' => $project->start_date === null,
        ]], 201);
    }

    /** Reference lists the grant forms need, in one round trip. */
    public function references(): JsonResponse
    {
        return response()->json(['data' => [
            'levels'     => DB::table('grant_levels')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'categories' => DB::table('grant_categories')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label', 'grant_level_id']),
            'roles'      => DB::table('grant_roles')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'statuses'   => DB::table('grant_statuses')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'funders'    => DB::table('funders')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'income_categories' => DB::table('income_categories')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'ip_types' => DB::table('ip_types')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'ip_registration_statuses' => DB::table('ip_registration_statuses')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'publication_types' => DB::table('publication_types')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'author_roles' => DB::table('author_roles')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'indexings' => DB::table('indexings')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'award_types' => DB::table('award_types')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
            'award_levels' => DB::table('award_levels')->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'code', 'label']),
        ]]);
    }
}
