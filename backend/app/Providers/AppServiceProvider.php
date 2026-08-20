<?php

namespace App\Providers;

use App\Models\HindexSnapshot;
use App\Models\KpiTarget;
use App\Models\ReportRun;
use App\Models\ResearchRecord;
use App\Models\StaffProfile;
use App\Models\Submission;
use App\Policies\HindexSnapshotPolicy;
use App\Policies\KpiTargetPolicy;
use App\Policies\ReportRunPolicy;
use App\Policies\ResearchRecordPolicy;
use App\Policies\StaffProfilePolicy;
use App\Policies\SubmissionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Registered explicitly rather than relying on discovery, so the mapping is greppable. */
    private const POLICIES = [
        Submission::class     => SubmissionPolicy::class,
        ResearchRecord::class => ResearchRecordPolicy::class,
        StaffProfile::class   => StaffProfilePolicy::class,
        KpiTarget::class      => KpiTargetPolicy::class,
        HindexSnapshot::class => HindexSnapshotPolicy::class,
        ReportRun::class      => ReportRunPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // General authenticated API traffic, keyed per user so one busy client
        // cannot exhaust the allowance for everyone behind the same campus NAT.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // Report generation is expensive; keep it deliberately tight.
        RateLimiter::for('reports', fn (Request $request) => Limit::perMinute(6)
            ->by($request->user()?->id ?: $request->ip()));

        // Password reset is an unauthenticated, email-sending endpoint.
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip()));
    }
}
