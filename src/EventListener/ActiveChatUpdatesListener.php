<?php

namespace App\EventListener;

use App\Agent\Agent;
use \App\Events\ActiveChatUpdates;
use App\Tui\State;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

final class ActiveChatUpdatesListener
{
    public function __construct(
        private Agent $agent,
    ) {

    }

    #[AsEventListener(event: ActiveChatUpdates::class)]
    public function onActiveChatUpdates(ActiveChatUpdates $event): void
    {
        // we will update active chat inside agent
        $this->agent->setActiveChat();
    }
}
