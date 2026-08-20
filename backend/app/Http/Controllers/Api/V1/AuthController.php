<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Sign in.
     *
     * Note there is no `role` parameter. ARAMS 1.0's login form posted the
     * role the user picked and rejected the attempt if it disagreed with the
     * stored role — harmless, but it presented role as something the client
     * asserts. Role is read from the account, never from the request.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['required', 'string'],
        ]);

        $this->throttleByIp($request);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->recordFailure($request, $credentials['email']);
            $this->audit->log(AuditLogger::LOGIN_FAILED, null, null, ['email' => $credentials['email']]);

            // Same message either way — never reveal whether the account exists.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            $this->recordFailure($request, $credentials['email']);

            throw ValidationException::withMessages([
                'email' => ['This account is inactive. Please contact the administrator.'],
            ]);
        }

        DB::table('login_attempts')->where('ip_address', $request->ip())->delete();

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('arams-api')->plainTextToken;

        auth()->setUser($user);
        $this->audit->log(AuditLogger::LOGIN, $user);

        return response()->json([
            'data' => [
                'token' => $token,
                'user'  => $this->profilePayload($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profilePayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->log(AuditLogger::LOGOUT, $request->user());
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['message' => 'Signed out.']]);
    }

    /**
     * Change your own password.
     *
     * Requires the current password — ARAMS 1.0 did not, so a hijacked session
     * became permanent account takeover. All other tokens are revoked.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        $this->audit->log(AuditLogger::PASSWORD_CHANGED, $user);

        return response()->json(['data' => ['message' => 'Password updated.']]);
    }

    private function profilePayload(User $user): array
    {
        $profile = $user->staffProfile;
        $affiliation = $profile?->affiliationOn(now());

        return [
            'id'    => $user->id,
            'email' => $user->email,
            'role'  => $user->role->value,
            'staff' => $profile ? [
                'id'        => $profile->id,
                'staff_no'  => $profile->staff_no,
                'full_name' => $profile->full_name,
                'title'     => $profile->title,
            ] : null,
            'faculty' => $affiliation?->faculty ? [
                'id'   => $affiliation->faculty->id,
                'code' => $affiliation->faculty->code,
                'name' => $affiliation->faculty->name,
            ] : null,
            // Faculties this user may validate for — empty for everyone but a
            // serving TDPP, and empty for a TDPP with no current appointment.
            'validates_faculties' => $profile
                ? $profile->currentAppointments()->pluck('faculty_id')->all()
                : [],
        ];
    }

    /** Carried over from 1.0: 5 failures per IP in 15 minutes. */
    private function throttleByIp(Request $request): void
    {
        $recent = DB::table('login_attempts')
            ->where('ip_address', $request->ip())
            ->where('attempted_at', '>', now()->subMinutes(15))
            ->count();

        if ($recent >= 5) {
            throw ValidationException::withMessages([
                'email' => ['Too many failed attempts. Try again in about 15 minutes.'],
            ]);
        }
    }

    private function recordFailure(Request $request, string $email): void
    {
        DB::table('login_attempts')->insert([
            'ip_address'   => $request->ip(),
            'email'        => $email,
            'attempted_at' => now(),
        ]);
    }
}
