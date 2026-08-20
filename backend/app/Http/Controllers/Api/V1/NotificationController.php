<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Notifications are stored as a type plus structured data and rendered at
 * display time, so the same event can be shown in English or Malay and linked
 * to the record it concerns. ARAMS 1.0 stored a finished English sentence
 * built with CONCAT inside whichever controller happened to fire it.
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = DB::table('notifications')
            ->where('notifiable_user_id', $request->user()->id)
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->limit($request->integer('limit', 50))
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'data'       => json_decode($n->data, true),
                'action_url' => $n->action_url,
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        return response()->json([
            'data' => $rows,
            'meta' => [
                'unread' => DB::table('notifications')
                    ->where('notifiable_user_id', $request->user()->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function markRead(string $id, Request $request): JsonResponse
    {
        $updated = DB::table('notifications')
            ->where('id', $id)
            // Scoped, so one user cannot mark another's notification read.
            ->where('notifiable_user_id', $request->user()->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);

        abort_if($updated === 0, 404, 'Notification not found.');

        return response()->json(['data' => ['message' => 'Marked as read.']]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = DB::table('notifications')
            ->where('notifiable_user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['marked' => $count]]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'data' => DB::table('notification_preferences')
                ->where('user_id', $request->user()->id)
                ->get(['type', 'in_app', 'email', 'digest']),
        ]);
    }

    public function updatePreference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'   => ['required', 'string', 'max:150'],
            'in_app' => ['required', 'boolean'],
            'email'  => ['required', 'boolean'],
            'digest' => ['boolean'],
        ]);

        DB::table('notification_preferences')->updateOrInsert(
            ['user_id' => $request->user()->id, 'type' => $validated['type']],
            [
                'in_app'     => $validated['in_app'],
                'email'      => $validated['email'],
                'digest'     => $validated['digest'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return response()->json(['data' => ['message' => 'Preference saved.']]);
    }
}
