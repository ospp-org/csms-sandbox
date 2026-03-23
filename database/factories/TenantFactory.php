<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'password' => 'password',
            'protocol_version' => config('sandbox.default_protocol_version', '0.2.1'),
            'validation_mode' => 'strict',
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function lenient(): static
    {
        return $this->state(['validation_mode' => 'lenient']);
    }
}
