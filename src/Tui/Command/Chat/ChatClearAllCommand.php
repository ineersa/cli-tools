<?php

declare(strict_types=1);

namespace App\Tui\Command\Chat;

use App\Agent\Agent;
use App\Service\ChatService;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;

class ChatClearAllCommand
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly State $state,
    ) {
    }

    public function clearAll(): never
    {
        $project = $this->state->getProject();
        if (!$project) {
            throw new ProblemException('No project selected');
        }

        $count = $this->chatService->deleteAllForProject($project->getId());

        throw new CompleteException("/chat clear-all \n Deleted $count chats for current project. If you want to start a new chat use /clear");
    }
}
