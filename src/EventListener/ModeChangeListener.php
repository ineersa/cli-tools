<?php

namespace App\EventListener;

use App\Agent\Agent;
use App\Events\ModeChangedEvent;
use PhpTui\Term\Terminal;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class ModeChangeListener
{
    public function __construct(
        private Agent $agent,
    ) {

    }

    #[AsEventListener(event: ModeChangedEvent::class)]
    public function onModeChangedEvent(ModeChangedEvent $event): void
    {
        $this->agent->setMode($event->mode);
    }
}
