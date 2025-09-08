<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Agent\Agent;
use App\Message\AssistantResponseReceived;
use App\Service\ChatService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AssistantResponseReceivedHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private ChatService $chatService,
    ) {
    }

    public function __invoke(AssistantResponseReceived $message): void
    {
        $this->logger->info('[AssistantResponseReceivedHandler] message received', [
            'requestId' => $message->requestId,
            'projectId' => $message->projectId,
            'chatId' => $message->chatId,
            'mode' => $message->mode->value,
            'response' => $message->response,
            'finishReason' => $message->finishReason,
            'promptTokens' => $message->promptTokens,
            'completionTokens' => $message->completionTokens,
            'totalTokens' => $message->totalTokens,
        ]);

        if (null === $message->chatId) {
            $chat = $this->chatService->getOpenChat(
                $message->projectId,
                $message->mode
            );
        } else {
            $chat = $this->chatService->chatRepository->find($message->chatId);
        }
        if (!$chat) {
            throw new \RuntimeException('Could not find active chat');
        }

        $chatTurn = $this->chatService->saveAssistantTurn(
            $chat,
            $message->response,
            $message->requestId,
            $message->finishReason,
            $message->promptTokens,
            $message->completionTokens,
            $message->totalTokens,
        );

        $this->logger->info('[AssistantResponseReceivedHandler] message processed', [
            'chatId' => $chat->getId(),
            'turnId' => $chatTurn->getId(),
            'requestId' => $message->requestId,
        ]);
    }
}
