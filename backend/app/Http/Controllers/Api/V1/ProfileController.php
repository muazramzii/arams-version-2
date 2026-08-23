<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Extensions we will store, and the MIME type each must actually be.
     *
     * ARAMS 1.0 trusted the extension from the uploaded filename and the
     * Content-Type from the request, then wrote the file into a directory
     * Apache served — so `shell.php` sent as `image/jpeg` became executable
     * code. That was the single worst defect in the audit.
     */
    private const IMAGE_TYPES = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    private const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->staffProfile;

        if (! $profile) {
            return response()->json([
                'title'  => 'Not found',
                'status' => 404,
                'detail' => 'Your account has no staff profile. Contact the administrator.',
            ], 404);
        }

        $affiliation = $profile->affiliationOn(now());

        return response()->json(['data' => [
            'id'        => $profile->id,
            'staff_no'  => $profile->staff_no,
            'full_name' => $profile->full_name,
            'title'     => $profile->title,
            'phone'     => $profile->phone,
            'specialisation' => $profile->specialisation,
            'cv_url'         => $profile->cv_url,
            'has_photo'      => $profile->profile_photo_path !== null,
            'managerial_position' => $profile->managerial_position,

            'position_id'          => $profile->position_id,
            'grade_id'             => $profile->grade_id,
            'researcher_status_id' => $profile->researcher_status_id,

            // Read-only here: changing faculty is an audited Admin action that
            // writes affiliation history, not a profile edit.
            'faculty' => $affiliation?->faculty ? [
                'id' => $affiliation->faculty->id,
                'code' => $affiliation->faculty->code,
                'name' => $affiliation->faculty->name,
                'since' => $affiliation->valid_from?->toDateString(),
            ] : null,

            'external_ids' => $profile->externalIds()->with('provider:id,code,label')->get()
                ->map(fn ($id) => [
                    'provider_id'   => $id->external_id_provider_id,
                    'provider_code' => $id->provider?->code,
                    'provider'      => $id->provider?->label,
                    'value'         => $id->value,
                ]),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $profile = $request->user()->staffProfile;
        abort_if($profile === null, 404, 'Your account has no staff profile.');

        $validated = $request->validate([
            'full_name'      => ['required', 'string', 'max:191'],
            'title'          => ['nullable', 'string', 'max:50'],
            'phone'          => ['nullable', 'string', 'max:30'],
            'specialisation' => ['nullable', 'string', 'max:255'],
            'cv_url'         => ['nullable', 'url', 'max:255'],
            'position_id'          => ['nullable', 'integer', 'exists:positions,id'],
            'grade_id'             => ['nullable', 'integer', 'exists:grades,id'],
            'researcher_status_id' => ['nullable', 'integer', 'exists:researcher_statuses,id'],
        ]);

        // staff_no and faculty are deliberately absent: both are institutional
        // facts, not self-service fields.
        $before = $profile->only(array_keys($validated));
        $profile->update($validated);

        $this->audit->logChange('staff_profile.updated', $profile, $before, $validated);

        return $this->show($request);
    }

    /** Replace the whole set, so removing one is a normal edit. */
    public function updateExternalIds(Request $request): JsonResponse
    {
        $profile = $request->user()->staffProfile;
        abort_if($profile === null, 404, 'Your account has no staff profile.');

        $validated = $request->validate([
            'ids'               => ['present', 'array'],
            'ids.*.provider_id' => ['required', 'integer', 'exists:external_id_providers,id'],
            'ids.*.value'       => ['required', 'string', 'max:191'],
        ]);

        // Two people claiming one ORCID is a real risk, and the unique index
        // enforces it — but a clear message beats a bare conflict.
        foreach ($validated['ids'] as $entry) {
            $takenBy = DB::table('staff_external_ids')
                ->where('external_id_provider_id', $entry['provider_id'])
                ->where('value', trim($entry['value']))
                ->where('staff_profile_id', '!=', $profile->id)
                ->exists();

            if ($takenBy) {
                $provider = DB::table('external_id_providers')
                    ->where('id', $entry['provider_id'])->value('label');

                return response()->json([
                    'title'  => 'Action not allowed',
                    'status' => 422,
                    'detail' => "That {$provider} is already recorded against another researcher. "
                              . 'Contact the administrator if this is wrong.',
                ], 422);
            }
        }

        DB::transaction(function () use ($profile, $validated) {
            $profile->externalIds()->delete();

            foreach ($validated['ids'] as $entry) {
                if (trim($entry['value']) === '') {
                    continue;
                }

                $profile->externalIds()->create([
                    'external_id_provider_id' => $entry['provider_id'],
                    'value'                   => trim($entry['value']),
                ]);
            }
        });

        $this->audit->log('staff_profile.external_ids_updated', $profile);

        return $this->show($request);
    }

    /**
     * Upload a profile photo.
     *
     * Four defences, none of which ARAMS 1.0 had:
     *  1. The extension is whitelisted, not taken from the filename.
     *  2. The real MIME type is read from the file's own bytes with finfo and
     *     must agree with that extension.
     *  3. getimagesize() must parse it as an actual image of sane dimensions.
     *  4. It is stored on the private disk under a generated name, outside the
     *     web root, and served back through a controller with a fixed
     *     Content-Type and nosniff.
     *
     * Without GD on this machine the file is not re-encoded, so a polyglot —
     * a valid image carrying an embedded payload — could still be stored. It
     * cannot execute: nothing interprets it, it is never in a served
     * directory, and it goes back with an explicit type and nosniff. Install
     * ext-gd and re-encode on upload to close that remaining gap too.
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $profile = $request->user()->staffProfile;
        abort_if($profile === null, 404, 'Your account has no staff profile.');

        $request->validate([
            'photo' => ['required', 'file', 'max:2048'],
        ]);

        $file = $request->file('photo');

        $extension = strtolower($file->getClientOriginalExtension());

        if (! array_key_exists($extension, self::IMAGE_TYPES)) {
            return $this->rejectUpload('Use a JPG, PNG or WebP image.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            return $this->rejectUpload('That image is larger than 2 MB.');
        }

        /**
         * The bytes decide, not the request headers.
         *
         * getMimeType() inspects the file's own content (finfo underneath) and
         * is deliberately used in place of getClientMimeType(), which is just
         * whatever the client typed. ARAMS 1.0 trusted the client value.
         */
        try {
            $actualMime = $file->getMimeType();
        } catch (\Throwable) {
            $actualMime = null;
        }

        if ($actualMime !== self::IMAGE_TYPES[$extension]) {
            return $this->rejectUpload(
                'That file is not the image type its name claims. Upload a real JPG, PNG or WebP.'
            );
        }

        $dimensions = @getimagesize($file->getRealPath());

        if ($dimensions === false || $dimensions[0] < 16 || $dimensions[1] < 16
            || $dimensions[0] > 5000 || $dimensions[1] > 5000) {
            return $this->rejectUpload('That image could not be read, or its dimensions are out of range.');
        }

        $old = $profile->profile_photo_path;

        // Generated name on the private disk — the uploaded filename is never
        // used, so it cannot contribute an extension or a path.
        $path = $file->storeAs(
            'profile-photos',
            Str::uuid() . '.' . $extension,
            'local',
        );

        $profile->update(['profile_photo_path' => $path]);

        if ($old && Storage::disk('local')->exists($old)) {
            Storage::disk('local')->delete($old);
        }

        $this->audit->log('staff_profile.photo_updated', $profile);

        return response()->json(['data' => ['has_photo' => true]]);
    }

    /** Served through PHP, never by the web server directly. */
    public function photo(Request $request, ?int $staffProfileId = null): StreamedResponse
    {
        $profile = $staffProfileId
            ? StaffProfile::findOrFail($staffProfileId)
            : $request->user()->staffProfile;

        abort_if($profile === null || $profile->profile_photo_path === null, 404);
        abort_unless($request->user()->can('view', $profile), 403);
        abort_unless(Storage::disk('local')->exists($profile->profile_photo_path), 404);

        $extension = strtolower(pathinfo($profile->profile_photo_path, PATHINFO_EXTENSION));

        return Storage::disk('local')->response(
            $profile->profile_photo_path,
            'photo.' . $extension,
            [
                'Content-Type'            => self::IMAGE_TYPES[$extension] ?? 'application/octet-stream',
                // Stops a browser second-guessing the type we just declared.
                'X-Content-Type-Options'  => 'nosniff',
                'Content-Disposition'     => 'inline',
                'Cache-Control'           => 'private, max-age=300',
            ],
        );
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        $profile = $request->user()->staffProfile;
        abort_if($profile === null, 404, 'Your account has no staff profile.');

        if ($profile->profile_photo_path) {
            Storage::disk('local')->delete($profile->profile_photo_path);
            $profile->update(['profile_photo_path' => null]);
            $this->audit->log('staff_profile.photo_removed', $profile);
        }

        return response()->json(['data' => ['has_photo' => false]]);
    }

    private function rejectUpload(string $detail): JsonResponse
    {
        return response()->json([
            'title'  => 'Action not allowed',
            'status' => 422,
            'detail' => $detail,
        ], 422);
    }
}
