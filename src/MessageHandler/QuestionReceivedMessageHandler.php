<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\QuestionReceivedMessage;
use App\Service\ChatService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class QuestionReceivedMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private ChatService $chatService,
    )
    {
    }

    public function __invoke(QuestionReceivedMessage $message): void
    {
        $this->logger->info('[QuestionReceivedMessageHandler] message received', [
            'requestId' => $message->requestId,
            'chatId' => $message->chatId,
            'projectId' => $message->projectId,
            'question' => $message->question,
            'mode' => $message->mode->value,
            'opts' => $message->opts,
        ]);
        if ($chatId = $message->chatId) {
            $chat = $this->chatService->chatRepository->find($chatId);
            if ($chat === null) {
                throw new UnrecoverableMessageHandlingException('No chat with id ' . $chatId);
            }
        } else {
            $chat = $this->chatService->createNewChat(
                $message->projectId,
                $message->question,
                $message->mode,
            );
        }

        $chatTurn = $this->chatService->saveUserTurn(
            $chat,
            $message->question
        );

        $this->logger->info('[QuestionReceivedMessageHandler] message processed', [
            'chatId' => $chat->getId(),
            'turnId' => $chatTurn->getId(),
        ]);
    }
}
