<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property-read TenantStation|null $station
 */
final class Tenant extends Authenticatable
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'email',
        'name',
        'password',
        'google_id',
        'protocol_version',
        'validation_mode',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function station(): HasOne
    {
        return $this->hasOne(TenantStation::class);
    }

    public function messageLog(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function conformanceResults(): HasMany
    {
        return $this->hasMany(ConformanceResult::class);
    }

    public function commandHistory(): HasMany
    {
        return $this->hasMany(CommandHistory::class);
    }
}
