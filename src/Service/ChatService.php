<?php

namespace App\Service;

use App\Agent\Mode;
use App\Entity\Chat;
use App\Entity\ChatTurn;
use App\Entity\Project;
use App\Repository\ChatRepository;
use App\Repository\ChatTurnRepository;
use App\Service\Chat\ChatStatus;
use App\Service\Chat\ChatTurnType;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;

class ChatService
{
    public readonly ChatRepository $chatRepository;
    private ?ObjectManager $manager;
    private ChatTurnRepository $chatTurnRepository;

    public function __construct(
        ManagerRegistry $managerRegistry,
    ) {
        $this->chatRepository = $managerRegistry->getRepository(Chat::class);
        $this->chatTurnRepository = $managerRegistry->getRepository(ChatTurn::class);
        $this->manager = $managerRegistry->getManagerForClass(Chat::class);
    }

    public function createNewChat(int $projectId, string $question, Mode $mode): Chat
    {
        $chat = new Chat();
        $chat->setProject($this->manager->getReference(Project::class, $projectId));
        $chat->setTitle(substr($question, 0, min(30, strlen($question))));
        $chat->setMode($mode);
        $chat->setStatus(ChatStatus::Open);

        $openedChats = $this->chatRepository->findBy([
            'project' => $projectId,
            'mode' => $mode,
            'status' => ChatStatus::Open,
        ]);
        foreach ($openedChats as $openedChat) {
            $openedChat->setStatus(ChatStatus::Archived);
        }

        $this->manager->persist($chat);
        $this->manager->flush();
        $this->manager->refresh($chat);

        return $chat;
    }

    public function saveUserTurn(Chat $chat, string $question, string $requestId): ChatTurn
    {
        $chatTurn = new ChatTurn();
        $chatTurn->setChat($chat);
        $chatTurn->setType(ChatTurnType::User);
        $idx = $chat->getLastTurn() ? $chat->getLastTurn()->getIdx() + 1 : 0;
        $chatTurn->setIdx($idx);
        $chatTurn->setContext($question);
        $chatTurn->setRequestId($requestId);
        $this->manager->persist($chatTurn);

        $chat->setLastTurn($chatTurn);
        $this->manager->flush();
        $this->manager->refresh($chatTurn);

        return $chatTurn;
    }

    public function saveAssistantTurn(
        Chat $chat,
        string $response,
        string $requestId,
        ?string $finishReason = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
        ?int $totalTokens = null
    ) : ChatTurn {
        $chatTurn = new ChatTurn();
        $chatTurn->setChat($chat);
        $chatTurn->setType(ChatTurnType::Assistant);
        $idx = $chat->getLastTurn() ? $chat->getLastTurn()->getIdx() + 1 : 0;
        $chatTurn->setIdx($idx);
        $chatTurn->setContext($response);
        $chatTurn->setRequestId($requestId);
        $chatTurn->setFinishReason($finishReason);
        $chatTurn->setPromptTokens($promptTokens);
        $chatTurn->setCompletionTokens($completionTokens);
        $chatTurn->setTotalTokens($totalTokens);
        $this->manager->persist($chatTurn);

        $chat->setLastTurn($chatTurn);
        $this->manager->flush();
        $this->manager->refresh($chatTurn);

        return $chatTurn;
    }

    public function getOpenChat(int $projectId, Mode $mode): ?Chat
    {
        return $this->chatRepository->findOneBy([
            'project' => $projectId,
            'mode' => $mode,
            'status' => ChatStatus::Open,
        ]);
    }

    public function resetOpenChat(Chat $chat): void
    {
        $chat->setStatus(ChatStatus::Archived);
        // TODO dispatch event to create summary
        $this->manager->flush();
        $this->manager->refresh($chat);
    }
}
