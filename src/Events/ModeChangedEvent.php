<?php

namespace App\Events;

use App\Agent\Mode;

final class ModeChangedEvent
{
    public function __construct(
        public readonly Mode $mode,
    ) {

    }
}
