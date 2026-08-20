<?php

namespace App\Services\Analytics;

use App\Models\User;

/**
 * What a given user may see, resolved once from the session.
 *
 * There are no role-named analytics endpoints in ARAMS 2.0. Naming a role in
 * the URL — /analytics/lecturer, /analytics/faculty — invites the client to
 * request the wrong one and puts the server in the position of refusing it.
 * Deriving scope here means the question cannot be asked incorrectly.
 */
class AnalyticsScope
{
    public const INSTITUTION = 'INSTITUTION';
    public const FACULTY     = 'FACULTY';
    public const STAFF       = 'STAFF';

    private function __construct(
        public readonly string $level,
        public readonly array $facultyIds,
        public readonly ?int $staffProfileId,
    ) {}

    public static function for(User $user): self
    {
        if ($user->isAdmin()) {
            return new self(self::INSTITUTION, [], null);
        }

        if ($user->isTdpp()) {
            $facultyIds = $user->staffProfile?->currentAppointments()
                ->pluck('faculty_id')->all() ?? [];

            // A TDPP with no current appointment sees nothing beyond their own
            // work — role alone confers no visibility.
            return $facultyIds === []
                ? new self(self::STAFF, [], $user->staffProfile?->id)
                : new self(self::FACULTY, $facultyIds, $user->staffProfile?->id);
        }

        return new self(self::STAFF, [], $user->staffProfile?->id);
    }

    public function isInstitution(): bool
    {
        return $this->level === self::INSTITUTION;
    }

    public function isFaculty(): bool
    {
        return $this->level === self::FACULTY;
    }

    public function isStaff(): bool
    {
        return $this->level === self::STAFF;
    }

    /** May this scope drill into a named faculty? */
    public function canSeeFaculty(int $facultyId): bool
    {
        return $this->isInstitution() || in_array($facultyId, $this->facultyIds, true);
    }
}
