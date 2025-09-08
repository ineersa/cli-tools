<?php

declare(strict_types=1);

namespace App\Message;

use App\Agent\Mode;

final class QuestionReceivedMessage
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $question,
        public readonly int $projectId,
        public readonly ?int $chatId = null,
        public readonly ?Mode $mode = null,
        /** @var array<string,mixed> */
        public readonly array $opts = [],
    ) {
    }
}
