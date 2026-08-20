<?php

use App\Http\Controllers\Api\V1\AuthController;
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
});
