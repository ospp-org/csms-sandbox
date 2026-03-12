<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Dto\HandlerContext;
use App\Dto\HandlerResult;

interface OsppHandler
{
    public function handle(HandlerContext $context): HandlerResult;
}
