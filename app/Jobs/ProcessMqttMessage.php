<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\MqttMessageDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ProcessMqttMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly string $stationId,
        public readonly string $topic,
        public readonly string $payload,
    ) {
        $this->onQueue('mqtt-messages');
    }

    public function handle(MqttMessageDispatcher $dispatcher): void
    {
        $envelope = json_decode($this->payload, true);

        if (! is_array($envelope)) {
            Log::warning("Invalid JSON payload from station {$this->stationId}");

            return;
        }

        $dispatcher->dispatch($this->stationId, $envelope);
    }
}
