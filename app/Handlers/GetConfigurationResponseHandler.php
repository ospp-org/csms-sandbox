<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\CommandService;
use App\Services\StationStateService;

final class GetConfigurationResponseHandler implements OsppHandler
{
    public function __construct(
        private readonly CommandService $commandService,
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $command = $this->commandService->findPendingByMessageId($context->messageId);

        if ($command !== null) {
            $command->update([
                'status' => 'responded',
                'response_payload' => $context->payload,
                'response_received_at' => now(),
            ]);
        }

        $configuration = $context->payload['configuration'] ?? [];

        if (is_array($configuration) && $configuration !== []) {
            $config = [];

            foreach ($configuration as $entry) {
                if (isset($entry['key'], $entry['value'])) {
                    $config[$entry['key']] = $entry['value'];
                }
            }

            if ($config !== []) {
                $this->stationState->setConfig($context->stationId, $config);
            }
        }

        return HandlerResult::acknowledged();
    }
}
