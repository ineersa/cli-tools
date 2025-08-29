<?php

declare(strict_types=1);

namespace App\Events;

use App\Agent\Mode;

final class QuestionReceivedEvent
{
    public function __construct(
        public readonly Mode $mode,
    ) {
    }
}
