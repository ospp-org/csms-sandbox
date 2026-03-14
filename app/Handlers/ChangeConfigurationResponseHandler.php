<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Contracts\OsppHandler;
use App\Dto\HandlerContext;
use App\Dto\HandlerResult;
use App\Services\CommandService;
use App\Services\StationStateService;

final class ChangeConfigurationResponseHandler implements OsppHandler
{
    public function __construct(
        private readonly CommandService $commandService,
        private readonly StationStateService $stationState,
    ) {}

    public function handle(HandlerContext $context): HandlerResult
    {
        $results = $context->payload['results'] ?? [];
        $command = $this->commandService->findPendingByMessageId($context->messageId);

        if ($command !== null) {
            $command->update([
                'status' => 'responded',
                'response_payload' => $context->payload,
                'response_received_at' => now(),
            ]);
        }

        // Atomic: only apply config if ALL keys were Accepted or RebootRequired
        if (is_array($results) && $results !== [] && $command !== null) {
            $allAccepted = true;

            foreach ($results as $result) {
                $keyStatus = $result['status'] ?? '';
                if ($keyStatus !== 'Accepted' && $keyStatus !== 'RebootRequired') {
                    $allAccepted = false;
                    break;
                }
            }

            if ($allAccepted) {
                $requestKeys = $command->payload['keys'] ?? [];
                $config = [];

                foreach ($requestKeys as $entry) {
                    if (isset($entry['key'], $entry['value'])) {
                        $config[$entry['key']] = $entry['value'];
                    }
                }

                if ($config !== []) {
                    $this->stationState->setConfig($context->stationId, $config);
                }
            }
        }

        return HandlerResult::acknowledged();
    }
}
