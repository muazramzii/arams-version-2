<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KpiPeriod;
use App\Services\Analytics\AnalyticsScope;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * One scoped endpoint family, not one per role.
 *
 * Scope comes from the token. A client may narrow within what it is already
 * entitled to see, never widen — the pattern ARAMS 1.0's analytics_detail.php
 * got right and which the rest of that system did not.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function overview(Request $request): JsonResponse
    {
        $scope = AnalyticsScope::for($request->user());

        return response()->json([
            'data' => $this->analytics->overview($scope, $this->period($request)),
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::exists('research_types', 'code')],
        ]);

        $scope = AnalyticsScope::for($request->user());

        return response()->json([
            'data' => $this->analytics->trends($scope, $request->query('type')),
        ]);
    }

    public function breakdown(Request $request): JsonResponse
    {
        // Whitelisted: the grouping column never comes from the request.
        $request->validate([
            'dimension' => ['required', Rule::in([
                'quartile', 'indexing', 'publication_type',
                'grant_level', 'grant_role', 'faculty', 'research_type',
            ])],
        ]);

        $scope = AnalyticsScope::for($request->user());

        return response()->json([
            'data' => $this->analytics->breakdown(
                $scope,
                $request->string('dimension')->toString(),
                $this->period($request),
            ),
        ]);
    }

    /** D5: anonymised institutional comparison, suppressed on small cohorts. */
    public function benchmark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
        ]);

        $scope = AnalyticsScope::for($request->user());

        return response()->json([
            'data' => $this->analytics->benchmark(
                $scope,
                (int) $validated['faculty_id'],
                $this->period($request),
            ),
        ]);
    }

    public function faculties(Request $request): JsonResponse
    {
        $scope = AnalyticsScope::for($request->user());

        return response()->json(['data' => $this->analytics->visibleFaculties($scope)]);
    }

    private function period(Request $request): ?KpiPeriod
    {
        $code = $request->query('period');

        return $code ? KpiPeriod::where('code', $code)->first() : null;
    }
}
