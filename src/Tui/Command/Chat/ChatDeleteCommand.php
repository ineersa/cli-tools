<?php

declare(strict_types=1);

namespace App\Tui\Command\Chat;

use App\Service\ChatService;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;

class ChatDeleteCommand
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {
    }

    public function delete(int $id): never
    {
        $entity = $this->chatService->chatRepository->find($id);
        if (!$entity) {
            throw new ProblemException('Chat not found');
        }
        $this->chatService->deleteChat($entity);

        throw new CompleteException("/chat delete \n Chat #".$id.' was successfully deleted.');
    }
}
