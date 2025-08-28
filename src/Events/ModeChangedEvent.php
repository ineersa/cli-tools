<?php

declare(strict_types=1);

namespace App\Events;

use App\Agent\Mode;

final class ModeChangedEvent
{
    public function __construct(
        public readonly Mode $mode,
    ) {
    }
}
