<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ConformanceResult;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConformanceResult>
 */
final class ConformanceResultFactory extends Factory
{
    protected $model = ConformanceResult::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'protocol_version' => '0.1.0',
            'action' => fake()->randomElement([
                'BootNotification', 'Heartbeat', 'StatusNotification',
            ]),
            'status' => 'not_tested',
        ];
    }

    public function passed(): static
    {
        return $this->state([
            'status' => 'passed',
            'last_tested_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'last_tested_at' => now(),
            'error_details' => [['path' => '/payload', 'message' => 'Test error']],
        ]);
    }
}
