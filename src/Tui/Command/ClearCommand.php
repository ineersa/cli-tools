<?php

declare(strict_types=1);

namespace App\Tui\Command;

use App\Agent\Agent;
use App\Service\ChatService;
use App\Tui\Component\TextContentComponentItems;
use App\Tui\Exception\CompleteException;
use App\Tui\State;

class ClearCommand implements CommandInterface
{
    public function __construct(
        private State $state,
        private ChatService $chatService,
        private Agent $agent,
    ) {
    }

    public function supports(string $command): bool
    {
        return '/clear' === trim($command);
    }

    public function execute(string $command): never
    {
        $this->state->setContentItems([
            TextContentComponentItems::getLogo(),
        ]);
        if ($chat = $this->agent->getActiveChat()) {
            $this->chatService->resetOpenChat($chat);
            $this->agent->setActiveChat();
        }


        throw new CompleteException("/clear \n Chat reset.");
    }
}
