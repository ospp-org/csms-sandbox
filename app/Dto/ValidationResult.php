<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class ValidationResult
{
    /**
     * @param array<int, array{path: string, message: string, keyword: string}> $errors
     */
    private function __construct(
        public bool $valid,
        public bool $skipped,
        public array $errors,
        public ?string $skipReason = null,
    ) {}

    public static function valid(): self
    {
        return new self(valid: true, skipped: false, errors: []);
    }

    /**
     * @param array<int, array{path: string, message: string, keyword: string}> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(valid: false, skipped: false, errors: $errors);
    }

    public static function skipped(string $reason): self
    {
        return new self(valid: true, skipped: true, errors: [], skipReason: $reason);
    }
}
