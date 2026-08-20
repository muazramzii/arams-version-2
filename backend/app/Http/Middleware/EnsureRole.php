<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse role gate for whole route groups.
 *
 * This is a first filter, never the authorization itself — per-record rules
 * live in Policies. ARAMS 1.0's failure was treating navigation as
 * authorization: 24 of 25 portal pages had no server-side check at all.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_active) {
            return response()->json([
                'title'  => 'Unauthenticated',
                'status' => 401,
                'detail' => 'A valid, active session is required.',
            ], 401);
        }

        if (! in_array($user->role->value, $roles, true)) {
            return response()->json([
                'title'  => 'Forbidden',
                'status' => 403,
                'detail' => 'Your role does not permit this action.',
            ], 403);
        }

        return $next($request);
    }
}
