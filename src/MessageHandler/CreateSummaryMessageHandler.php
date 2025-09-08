<?php

namespace App\MessageHandler;

use App\Message\CreateSummaryMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateSummaryMessageHandler{
    public function __invoke(CreateSummaryMessage $message): void
    {
        // do something with your message
    }
}
