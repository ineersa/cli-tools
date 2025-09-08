<?php

declare(strict_types=1);

namespace App\Tui\Command\Chat;

use App\Service\ChatService;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;

class ChatRestoreCommand
{

    public function __construct(
        private readonly ChatService $chatService,
        private readonly State $state,
    ) {
    }

    public function restore(int $id): never
    {
        $project = $this->state->getProject();
        if (!$project) {
            throw new ProblemException('No project selected');
        }

        $entity = $this->chatService->chatRepository
            ->findOneBy(
                [
                    'id' => $id,
                    'project' => $project,
                ]
            );
        if (!$entity) {
            throw new ProblemException('Chat not found');
        }
        $this->chatService->restoreChat($entity);

        throw new CompleteException("/chat restore \n Chat #".$id.' was restored and set to open.');
    }
}
