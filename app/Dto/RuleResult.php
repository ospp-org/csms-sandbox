<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class RuleResult
{
    public function __construct(
        public bool $passed,
        public string $rule,
        public ?string $detail = null,
    ) {}
}
