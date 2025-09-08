<?php

namespace App\Service;

use App\Agent\Agent;
use App\Agent\Mode;
use App\Entity\Chat;
use App\Entity\ChatTurn;
use App\Entity\Project;
use App\Events\ActiveChatUpdates;
use App\Llm\Limits;
use App\Message\CreateSummaryMessage;
use App\Repository\ChatRepository;
use App\Repository\ChatTurnRepository;
use App\Service\Chat\ChatStatus;
use App\Service\Chat\ChatTurnType;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ChatService
{
    public readonly ChatRepository $chatRepository;

    public const COMPRESS_THRESHOLD = 0.5;
    private ?ObjectManager $manager;
    private ChatTurnRepository $chatTurnRepository;

    public function __construct(
        ManagerRegistry             $managerRegistry,
        private MessageBusInterface $messageBus,
        private EventDispatcherInterface         $eventDispatcher,
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
        $this->messageBus->dispatch(new CreateSummaryMessage($chat->getId()));

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
        $this->messageBus->dispatch(new CreateSummaryMessage($chat->getId()));
        $this->manager->flush();
        $this->manager->refresh($chat);
    }

    public function updateChat(Chat $chat): void
    {
        $this->manager->flush();
        $this->manager->refresh($chat);
    }

    public function deleteChat(Chat $chat): void
    {
        if ($chat->getLastTurn() !== null) {
            $chat->setLastTurn(null);
            $this->manager->flush();
        }

        $this->manager->remove($chat);
        $this->manager->flush();
        $this->eventDispatcher->dispatch(new ActiveChatUpdates());
    }

    public function restoreChat(Chat $chat): void
    {
        // Archive any currently open chats for same project and mode
        $openedChats = $this->chatRepository->findBy([
            'project' => $chat->getProject()?->getId(),
            'mode' => $chat->getMode(),
            'status' => ChatStatus::Open,
        ]);
        foreach ($openedChats as $openedChat) {
            if ($openedChat->getId() !== $chat->getId()) {
                $openedChat->setStatus(ChatStatus::Archived);
            }
        }
        $chat->setStatus(ChatStatus::Open);
        $this->manager->flush();
        $this->manager->refresh($chat);
        $this->eventDispatcher->dispatch(new ActiveChatUpdates());
    }

    public function deleteAllForProject(int $projectId): int
    {
        $chats = $this->chatRepository->findBy(['project' => $projectId]);
        $count = 0;
        foreach ($chats as $chat) {
            if ($chat->getLastTurn() !== null) {
                $chat->setLastTurn(null);
            }
            $this->manager->remove($chat);
            ++$count;
        }
        // One flush handles both nulling last_turn and removals
        $this->manager->flush();
        $this->eventDispatcher->dispatch(new ActiveChatUpdates());
        return $count;
    }

    /**
     * @param Chat $chat
     * @param int $maxInputTokens
     * @return array{messages: string[], summary: null|string, turns: ChatTurn[]}
     */
    public function loadHistory(Chat $chat, int $maxInputTokens): array
    {
        $threshold = (int) floor($maxInputTokens * self::COMPRESS_THRESHOLD);
        $threshold = $threshold * 0.9; // adding small cushion in case our estimates are wrong
        $turns = $chat->getChatTurns()->toArray();
        // descending, i will reverse it later
        usort($turns, function($a, $b) {
            return ($b->getIdx() <=> $a->getIdx());
        });

        $messages = [];
        $summary = null;
        $usage = 0;
        foreach ($turns as $turn) {
            // TODO exclude tool turns?
            $role = $turn->getType() === ChatTurnType::User ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $turn->getContext() ?? ''];
            // very rough estimate
            $usage += (int)floor((mb_strlen($turn->getContext() ?? '') / 4));
            if ($usage >= $threshold) {
                $messages = array_reverse($messages);
                $summary = $chat->getSummary();
                break;
            }
        }

        return ['messages' => $messages, 'summary' => $summary, 'turns' => $turns];
    }

    public function find(int $id): ?Chat
    {
        $chat = $this->chatRepository->find($id);
        if (null !== $chat) {
            $this->manager->refresh($chat);
        }
        return $chat;
    }
}
