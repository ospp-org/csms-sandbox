<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class HandlerResult
{
    /**
     * @param array<string, mixed> $responsePayload
     */
    private function __construct(
        public bool $success,
        public array $responsePayload,
        public ?string $errorCode = null,
        public ?string $errorText = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function accepted(array $payload): self
    {
        return new self(true, $payload);
    }

    public static function rejected(string $code, string $text): self
    {
        return new self(false, [], $code, $text);
    }

    public static function acknowledged(): self
    {
        return new self(true, []);
    }
}
