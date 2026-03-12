<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommandHistory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommandHistory>
 */
final class CommandHistoryFactory extends Factory
{
    protected $model = CommandHistory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'station_id' => 'stn_' . fake()->hexColor(),
            'action' => fake()->randomElement(['StartService', 'StopService', 'Reset']),
            'message_id' => 'msg_' . Str::random(12),
            'payload' => ['resetType' => 'Soft'],
            'status' => 'sent',
        ];
    }

    public function responded(): static
    {
        return $this->state([
            'status' => 'responded',
            'response_payload' => ['status' => 'Accepted'],
            'response_received_at' => now(),
        ]);
    }
}
