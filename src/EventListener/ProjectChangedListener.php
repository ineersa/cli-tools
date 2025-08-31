<?php

namespace App\EventListener;

use App\Agent\Agent;
use App\Events\ProjectChangedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class ProjectChangedListener
{
    public function __construct(
        private Agent $agent,
    ) {
    }

    #[AsEventListener(event: ProjectChangedEvent::class)]
    public function onProjectChangedEvent($event): void
    {
        $this->agent->setProject($event->project);
    }
}
