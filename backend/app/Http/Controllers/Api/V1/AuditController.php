<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The audit log, scoped.
 *
 * ARAMS 1.0 exposed the whole log to Admin and — unusually for that codebase —
 * actually guarded it: audit_log.php was the single page of 25 that checked
 * the caller's role. Here a lecturer can additionally see their own activity,
 * which is a reasonable transparency improvement rather than a loosening.
 */
class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AuditEvent::query()->with('actor:id,email')->latest('created_at');

        if (! $user->isAdmin()) {
            // Everyone else sees only what they themselves did.
            $query->where('actor_user_id', $user->id);
        }

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($type = $request->query('auditable_type')) {
            $query->where('auditable_type', $type);
        }

        return response()->json([
            'data' => $query->limit($request->integer('limit', 100))->get()->map(fn ($e) => [
                'id'             => $e->id,
                'action'         => $e->action,
                'actor'          => $e->actor?->email,
                'actor_role'     => $e->actor_role,
                'auditable_type' => $e->auditable_type ? class_basename($e->auditable_type) : null,
                'auditable_id'   => $e->auditable_id,
                'changes'        => $e->changes,
                'context'        => $e->context,
                'created_at'     => $e->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
