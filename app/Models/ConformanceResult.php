<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConformanceResult extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'protocol_version',
        'action',
        'status',
        'last_tested_at',
        'last_payload',
        'error_details',
        'behavior_checks',
    ];

    protected function casts(): array
    {
        return [
            'last_tested_at' => 'datetime',
            'last_payload' => 'array',
            'error_details' => 'array',
            'behavior_checks' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForVersion(Builder $query, string $version): Builder
    {
        return $query->where('protocol_version', $version);
    }
}
