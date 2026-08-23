<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GrantProjectController;
use App\Http\Controllers\Api\V1\KpiController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ResearchRecordController;
use App\Http\Controllers\Api\V1\SubmissionController;
use Illuminate\Support\Facades\Route;

/**
 * ARAMS 2.0 API — mounted at /api/v1 (see bootstrap/app.php).
 *
 * Resource-oriented, not action-oriented. ARAMS 1.0's api/ folder was a flat
 * list of verbs — validate.php, assign_kpi.php, toggle_user.php — with no
 * consistent request or response shape between them.
 *
 * Scope is always derived from the authenticated user. There are no
 * role-named paths such as /analytics/lecturer: naming a role in the URL
 * invites the client to request the wrong one and puts the server in the
 * position of refusing it.
 */

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::put('password', [AuthController::class, 'changePassword'])->name('auth.password');
    });
});

Route::middleware('auth:sanctum')->group(function () {

    // ── Research records ────────────────────────────────────────────────
    Route::apiResource('research-records', ResearchRecordController::class)
        ->parameters(['research-records' => 'researchRecord']);

    Route::post('research-records/{id}/restore', [ResearchRecordController::class, 'restore'])
        ->whereNumber('id')
        ->name('research-records.restore');

    /**
     * ── Grant projects ──────────────────────────────────────────────────
     *
     * The shared institutional object. A lecturer searches for the code
     * before claiming participation, so two people on one grant attach to
     * one project instead of creating two — the defect that duplicated
     * eleven codes and RM 420,000 in ARAMS 1.0.
     */
    Route::get('reference-data', [GrantProjectController::class, 'references'])
        ->name('reference-data');
    Route::get('grant-projects', [GrantProjectController::class, 'index'])
        ->name('grant-projects.index');
    Route::post('grant-projects', [GrantProjectController::class, 'store'])
        ->name('grant-projects.store');

    // ── Submissions and validation ──────────────────────────────────────
    Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions.index');

    // The TDPP validation queue. Declared before {submission} so the literal
    // segments are not swallowed by the model binding.
    Route::get('submissions/queue', [SubmissionController::class, 'queue'])
        ->name('submissions.queue');

    Route::get('submissions/coverage-gaps', [SubmissionController::class, 'coverageGaps'])
        ->name('submissions.coverage-gaps');

    Route::get('submissions/{submission}', [SubmissionController::class, 'show'])
        ->name('submissions.show');

    Route::get('submissions/{submission}/transitions', [SubmissionController::class, 'transitions'])
        ->name('submissions.transitions');

    // Owner actions.
    Route::post('submissions/{submission}/submit', [SubmissionController::class, 'submit'])
        ->name('submissions.submit');
    Route::post('submissions/{submission}/withdraw', [SubmissionController::class, 'withdraw'])
        ->name('submissions.withdraw');

    /**
     * Reviewer actions. D1: TDPP only, and only for a faculty where they hold
     * a current appointment. The route group says TDPP; SubmissionPolicy
     * additionally checks the appointment and refuses self-review.
     *
     * There is deliberately no Admin approval endpoint anywhere in this file.
     */
    Route::middleware('role:TDPP')->group(function () {
        Route::post('submissions/{submission}/claim', [SubmissionController::class, 'claim'])
            ->name('submissions.claim');
        Route::post('submissions/{submission}/approve', [SubmissionController::class, 'approve'])
            ->name('submissions.approve');
        Route::post('submissions/{submission}/reject', [SubmissionController::class, 'reject'])
            ->name('submissions.reject');
        Route::post('submissions/{submission}/request-revision', [SubmissionController::class, 'requestRevision'])
            ->name('submissions.request-revision');
    });

    // ── KPI ─────────────────────────────────────────────────────────────
    Route::prefix('kpi')->name('kpi.')->group(function () {
        Route::get('periods', [KpiController::class, 'periods'])->name('periods');
        Route::get('measures', [KpiController::class, 'measures'])->name('measures');

        Route::get('targets', [KpiController::class, 'targets'])->name('targets');
        Route::post('targets', [KpiController::class, 'storeTarget'])
            ->middleware('role:TDPP,Admin')->name('targets.store');

        Route::get('assignments', [KpiController::class, 'assignments'])->name('assignments');
        Route::post('assignments', [KpiController::class, 'assign'])
            ->middleware('role:TDPP,Admin')->name('assignments.store');
        Route::delete('assignments/{assignment}', [KpiController::class, 'unassign'])
            ->middleware('role:TDPP,Admin')->name('assignments.destroy');

        // Which records actually credited a target — progress you can audit.
        Route::get('assignments/{assignment}/contributions', [KpiController::class, 'contributions'])
            ->name('assignments.contributions');
    });

    // ── Analytics ───────────────────────────────────────────────────────
    // Deliberately not /analytics/lecturer, /analytics/faculty etc.
    // Scope is resolved from the token, so it cannot be requested wrongly.
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('overview', [AnalyticsController::class, 'overview'])->name('overview');
        Route::get('trends', [AnalyticsController::class, 'trends'])->name('trends');
        Route::get('breakdown', [AnalyticsController::class, 'breakdown'])->name('breakdown');
        Route::get('benchmark', [AnalyticsController::class, 'benchmark'])->name('benchmark');
        Route::get('faculties', [AnalyticsController::class, 'faculties'])->name('faculties');
    });

    // ── Reports ─────────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('definitions', [ReportController::class, 'definitions'])->name('definitions');
        Route::get('runs', [ReportController::class, 'index'])->name('runs.index');
        Route::post('runs', [ReportController::class, 'store'])
            ->middleware('throttle:reports')->name('runs.store');
        Route::get('runs/{reportRun}/download', [ReportController::class, 'download'])
            ->name('runs.download');
    });

    // ── Notifications ───────────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::get('preferences', [NotificationController::class, 'preferences'])->name('preferences');
        Route::put('preferences', [NotificationController::class, 'updatePreference'])->name('preferences.update');
        Route::post('{id}/read', [NotificationController::class, 'markRead'])->name('read');
    });

    // ── Audit ───────────────────────────────────────────────────────────
    Route::get('audit-events', [AuditController::class, 'index'])->name('audit.index');

    /**
     * ── Administration ──────────────────────────────────────────────────
     *
     * Appointing a TDPP is an Admin power, but it is NOT a validation power:
     * D1 stands, and an Admin still cannot approve anything. What they can do
     * is decide who validates — which is the only remedy when a faculty has
     * nobody, as FKAAS currently does with 77 lecturers.
     */
    Route::middleware('role:Admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('faculties', [AdminController::class, 'faculties'])->name('faculties');
        Route::post('faculties/{faculty}/leaders', [AdminController::class, 'appointLeader'])
            ->name('faculties.appoint');
        Route::delete('faculty-leaders/{facultyLeader}', [AdminController::class, 'endLeader'])
            ->name('leaders.end');
        Route::get('appointable-staff', [AdminController::class, 'appointableStaff'])
            ->name('appointable-staff');

        Route::get('users', [AdminController::class, 'users'])->name('users');
        Route::put('users/{user}/activation', [AdminController::class, 'setUserActivation'])
            ->name('users.activation');
        Route::put('users/{user}/role', [AdminController::class, 'setUserRole'])->name('users.role');

        Route::get('data-quality', [AdminController::class, 'dataQuality'])->name('data-quality');
    });
});
