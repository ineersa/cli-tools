<?php

declare(strict_types=1);

namespace App\Tui\Command\Chat;

use App\Service\ChatService;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;

class ChatSummaryCommand
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {
    }

    public function summary(int $id): never
    {
        $entity = $this->chatService->find($id);
        if (!$entity) {
            throw new ProblemException('Chat not found');
        }

        $summary = $entity->getSummary();
        if (!$summary) {
            throw new CompleteException("/chat summary \n No summary yet for chat #".$id);
        }

        throw new CompleteException("/chat summary \n Chat #$id Summary:\n\n".$summary);
    }
}
