<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon|null $last_connected_at
 * @property \Illuminate\Support\Carbon|null $last_boot_at
 */
final class TenantStation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'station_id',
        'mqtt_username',
        'mqtt_password_hash',
        'mqtt_password_encrypted',
        'protocol_version',
        'is_connected',
        'last_connected_at',
        'last_boot_at',
        'bay_count',
        'firmware_version',
        'station_model',
        'station_vendor',
    ];

    protected function casts(): array
    {
        return [
            'is_connected' => 'boolean',
            'last_connected_at' => 'datetime',
            'last_boot_at' => 'datetime',
            'mqtt_password_encrypted' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('is_connected', true);
    }

    public function scopeForStation(Builder $query, string $stationId): Builder
    {
        return $query->where('station_id', $stationId);
    }
}
