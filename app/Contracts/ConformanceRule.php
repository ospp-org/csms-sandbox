<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Dto\HandlerContext;
use App\Dto\RuleResult;
use App\Services\StationStateService;

interface ConformanceRule
{
    public function name(): string;

    public function check(HandlerContext $context, StationStateService $state): RuleResult;
}
