<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for every controlled vocabulary.
 *
 * ARAMS 1.0 kept these in JavaScript, in duplicated PHP arrays, and in schema
 * ENUMs simultaneously. Nothing reconciled the three, which is how 'University'
 * and 'Universiti' both entered tbl_grant and how 52 of 57 report rows ended up
 * with an empty report_type. Here they are rows with foreign keys pointing at
 * them, so a value that does not exist cannot be stored.
 */
abstract class ReferenceModel extends Model
{
    protected $fillable = ['code', 'label', 'label_ms', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    /** Resolve by stable code rather than by auto-increment id. */
    public static function byCode(string $code): ?static
    {
        return static::where('code', $code)->first();
    }
}
