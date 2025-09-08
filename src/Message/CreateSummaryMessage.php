<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'summary')]
final class CreateSummaryMessage
{
    public function __construct(
        public readonly int $chatId,
    ) {
    }
}
