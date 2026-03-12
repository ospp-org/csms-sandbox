<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class HandlerContext
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $envelope
     */
    public function __construct(
        public string $tenantId,
        public string $stationId,
        public string $action,
        public string $messageId,
        public string $messageType,
        public array $payload,
        public array $envelope,
        public string $protocolVersion,
    ) {}
}
