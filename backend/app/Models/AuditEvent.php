<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_user_id', 'actor_role', 'action', 'auditable_type', 'auditable_id', 'changes', 'context', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'context' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('audit_events is append-only'));
        static::deleting(fn () => throw new \RuntimeException('audit_events is append-only'));
    }
}
