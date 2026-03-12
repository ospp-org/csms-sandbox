<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class CommandResult
{
    /**
     * @param array<int, array{path: string, message: string, keyword: string}> $validationErrors
     */
    private function __construct(
        public bool $success,
        public ?string $commandId,
        public ?string $messageId,
        public ?string $errorCode,
        public ?string $errorText,
        public array $validationErrors = [],
    ) {}

    public static function sent(string $commandId, string $messageId): self
    {
        return new self(success: true, commandId: $commandId, messageId: $messageId, errorCode: null, errorText: null);
    }

    public static function error(string $code, string $text = ''): self
    {
        return new self(success: false, commandId: null, messageId: null, errorCode: $code, errorText: $text);
    }

    /**
     * @param array<int, array{path: string, message: string, keyword: string}> $errors
     */
    public static function validationError(array $errors): self
    {
        return new self(success: false, commandId: null, messageId: null, errorCode: 'VALIDATION_ERROR', errorText: 'Payload validation failed', validationErrors: $errors);
    }
}
