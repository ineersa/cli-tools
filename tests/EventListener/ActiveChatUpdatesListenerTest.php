<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Agent\Agent;
use App\EventListener\ActiveChatUpdatesListener;
use App\Events\ActiveChatUpdates;
use PHPUnit\Framework\TestCase;

final class ActiveChatUpdatesListenerTest extends TestCase
{
    public function testOnActiveChatUpdatesCallsSetActiveChatOnAgent(): void
    {
        $agent = $this->createMock(Agent::class);
        $listener = new ActiveChatUpdatesListener($agent);

        $agent->expects($this->once())
            ->method('setActiveChat');

        $listener->onActiveChatUpdates(new ActiveChatUpdates());
    }
}
