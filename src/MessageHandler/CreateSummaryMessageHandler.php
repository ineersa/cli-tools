<?php

namespace App\MessageHandler;

use App\Agent\Agent;
use App\Agent\PromptManager;
use App\Llm\Limits;
use App\Message\CreateSummaryMessage;
use App\Service\ChatService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class CreateSummaryMessageHandler
{
    public function __construct(
        private ChatService $chatService,
        private Agent $agent,
        private PromptManager $promptManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateSummaryMessage $message): void
    {
        $this->logger->info('[CreateSummaryMessageHandler] message received', [
            'chatId' => $message->chatId,
        ]);
        $chat = $this->chatService->chatRepository->find($message->chatId);
        if (!$chat) {
            throw new UnrecoverableMessageHandlingException("Chat doesn't exist");
        }

        $history = $this->chatService->loadHistory($chat, $this->agent->smallModel->getLimit(Limits::MaxInputTokens));
        $systemPrompt = $this->promptManager->getSummarizerPrompt();
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        if ($chat->getSummary()) {
            $messages[] = [
                'role' => 'system',
                'content' => 'Conversation so far (previous summary): ' . $chat->getSummary(),
            ];
        }
        foreach ($history['messages'] ?? [] as $m) {
            $messages[] = $m;
        }
        $messages[] = [
            'role' => 'user',
            'content' => 'Summarize the prior conversation only. Do not include this request in the summary.',
        ];

        $response = $this->agent->smallModel->completion(
            [
                'messages' => $messages
            ]
        );
        $choice = $response->choices[0] ?? null;
        $content = trim($choice->message->content ?? '');

        if ($content === '') {
            $this->logger->error('Empty content received', [
                'chatId' => $chat->getId(),
                'finishReason' => $choice->finishReason ?? 'UNKNOWN'
            ]);
            throw new \RuntimeException('Summarizer returned empty content');
        }


        $chat->setSummary($response->choices[0]->message->content);
        $this->chatService->updateChat($chat);

        $this->logger->info('[CreateSummaryMessageHandler] message processed', [
            'chatId' => $message->chatId,
        ]);
    }
}
