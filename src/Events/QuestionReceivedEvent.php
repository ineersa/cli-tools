<?php

declare(strict_types=1);

namespace App\Events;

final class QuestionReceivedEvent
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $question,
    ) {
    }
}
