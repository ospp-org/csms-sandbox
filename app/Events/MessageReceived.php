<?php
declare(strict_types=1);
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<string, mixed> $message
     */
    public function __construct(
        public readonly string $stationId,
        public readonly array $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('station.' . $this->stationId)];
    }
}
