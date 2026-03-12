<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $response_payload
 * @property \Illuminate\Support\Carbon|null $response_received_at
 */
final class CommandHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'command_history';

    protected $fillable = [
        'tenant_id',
        'station_id',
        'action',
        'message_id',
        'payload',
        'response_payload',
        'response_received_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_payload' => 'array',
            'response_received_at' => 'datetime',
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

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }
}
