<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class AssistantResponseReceived
{
     public function __construct(
         public int $chatId,
         public string $requestId,
         public string $response,
         public ?string $finishReason = null,
         public ?string $promptTokens = null,
         public ?string $completionTokens = null,
         public ?string $totalTokens = null,
     ) {
     }
}
