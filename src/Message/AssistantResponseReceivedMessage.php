<?php

declare(strict_types=1);

namespace App\Message;

use App\Agent\Mode;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class AssistantResponseReceivedMessage
{
    public function __construct(
        public int $projectId,
        public string $requestId,
        public string $response,
        public Mode $mode,
        public ?int $chatId = null,
        public ?string $finishReason = null,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $totalTokens = null,
    ) {
    }
}
