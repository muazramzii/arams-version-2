<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One profile per person, whatever their role.
 *
 * ARAMS 1.0 split this across tbl_lecturer, tbl_tdpp and tbl_admin, which is
 * why a TDPP could not have a profile page and why api/update_user.php wrote
 * a name change to two of the three tables and silently missed TDPPs.
 */
class StaffProfile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'staff_no', 'full_name', 'title', 'position_id', 'grade_id',
        'researcher_status_id', 'phone', 'specialisation', 'managerial_position',
        'profile_photo_path', 'cv_url', 'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'managerial_position' => 'boolean',
            'is_archived'         => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(StaffExternalId::class);
    }

    public function affiliations(): HasMany
    {
        return $this->hasMany(StaffAffiliation::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(FacultyLeader::class);
    }

    public function currentAppointments(): HasMany
    {
        return $this->appointments()
            ->whereNull('valid_to')
            ->whereDate('valid_from', '<=', now());
    }

    public function researchRecords(): HasMany
    {
        return $this->hasMany(ResearchRecord::class, 'owner_staff_profile_id');
    }

    public function hindexSnapshots(): HasMany
    {
        return $this->hasMany(HindexSnapshot::class);
    }

    public function kpiAssignments(): HasMany
    {
        return $this->hasMany(KpiAssignment::class);
    }

    /**
     * The affiliation in force on a given date.
     *
     * This is the method that fixes the 1.0 defect where analytics attributed
     * research through the lecturer's *current* faculty: when lecturer 1
     * transferred FSKTM -> FKAAB, 37 records dating back to 2016 moved with
     * her and both faculties' published history silently changed.
     */
    public function affiliationOn(?\DateTimeInterface $date): ?StaffAffiliation
    {
        $date ??= now();

        return $this->affiliations()
            ->where('is_primary', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    public function currentFacultyId(): ?int
    {
        return $this->affiliationOn(now())?->faculty_id;
    }

    public function scopeActiveStaff(Builder $query): Builder
    {
        return $query->where('is_archived', false)
            ->whereHas('user', fn (Builder $q) => $q->where('is_active', true));
    }
}
