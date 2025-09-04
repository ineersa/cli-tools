<?php

namespace App\MessageHandler;

use App\Message\AssistantResponseReceived;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AssistantResponseReceivedHandler{
    public function __invoke(AssistantResponseReceived $message): void
    {
        // do something with your message
    }
}
