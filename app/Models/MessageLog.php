<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MessageLog extends Model
{
    public $timestamps = false;

    protected $table = 'message_log';

    protected $fillable = [
        'tenant_id',
        'station_id',
        'direction',
        'action',
        'message_id',
        'message_type',
        'payload',
        'schema_valid',
        'validation_errors',
        'processing_time_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'validation_errors' => 'array',
            'schema_valid' => 'boolean',
            'created_at' => 'datetime',
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

    public function scopeInbound(Builder $query): Builder
    {
        return $query->where('direction', 'inbound');
    }

    public function scopeOutbound(Builder $query): Builder
    {
        return $query->where('direction', 'outbound');
    }
}
