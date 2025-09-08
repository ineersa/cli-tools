<?php

declare(strict_types=1);

namespace App\Tui\Command\Chat;

use App\Service\ChatService;
use App\Tui\Component\TableListComponent;
use App\Tui\Exception\CompleteException;
use App\Tui\Exception\ProblemException;
use App\Tui\State;
use Symfony\Component\Serializer\SerializerInterface;

class ChatListCommand
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly State $state,
    ) {
    }

    public function list(): never
    {
        $project = $this->state->getProject();
        if (!$project) {
            throw new ProblemException('No project selected');
        }
        $chats = $this->chatService->chatRepository->findByProjectActiveFirst($project);

        if (empty($chats)) {
            throw new CompleteException("/chat list \n No chats were found for current project");
        }

        $data = [];
        foreach ($chats as $chat) {
            $data[] = [
                'id' => $chat->getId(),
                'title' => $chat->getTitle(),
                'mode' => $chat->getMode()?->value,
                'status' => $chat->getStatus()?->value,
                'updated_at' => $chat->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        $component = new TableListComponent(
            $this->state,
            $data
        );
        $this->state->setDynamicIslandComponents([
            TableListComponent::NAME => $component,
        ]);
        // overtake controls for table
        $this->state->setEditing(false);

        throw new CompleteException("/chat list \n Found ".\count($chats).' chats');
    }
}
